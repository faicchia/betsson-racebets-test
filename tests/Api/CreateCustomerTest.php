<?php

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

beforeEach(function () {
    $this->client = self::createClient();
    self::getContainer()->get(App\Database\Connection::class)->execute('DELETE FROM customers');
});

function postCustomer(KernelBrowser $client, array|string $payload): array
{
    $client->request(
        'POST',
        '/api/customers',
        server: ['CONTENT_TYPE' => 'application/json'],
        content: is_string($payload) ? $payload : json_encode($payload),
    );

    return json_decode($client->getResponse()->getContent(), true) ?? [];
}

function validCustomer(array $overrides = []): array
{
    return [
        'email' => 'jane.doe@example.com',
        'firstName' => 'Jane',
        'lastName' => 'Doe',
        'gender' => 'female',
        'country' => 'DE',
        ...$overrides,
    ];
}

it('creates a customer', function () {
    $body = postCustomer($this->client, validCustomer());

    expect($this->client->getResponse()->getStatusCode())->toBe(201)
        ->and($body['id'])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[0-9a-f]{4}-[0-9a-f]{12}$/')
        ->and($body)->toMatchArray([
            'email' => 'jane.doe@example.com',
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'gender' => 'female',
            'country' => 'DE',
            'realBalance' => '0.00',
            'bonusBalance' => '0.00',
        ])
        ->and($body['bonusPercent'])->toBeInt()->toBeGreaterThanOrEqual(5)->toBeLessThanOrEqual(20)
        ->and($body['createdAt'])->toBe($body['updatedAt']);

    expect($this->client->getResponse()->headers->get('Location'))->toBe('/api/customers/'.$body['id']);
});

it('stores the customer', function () {
    $body = postCustomer($this->client, validCustomer());

    $row = self::getContainer()->get(App\Database\Connection::class)
        ->fetchOne('SELECT email, bonus_percent FROM customers WHERE id = UUID_TO_BIN(?)', [$body['id']]);

    expect($row)->toBe(['email' => 'jane.doe@example.com', 'bonus_percent' => $body['bonusPercent']]);
});

it('rejects an email that is already taken, ignoring case', function () {
    postCustomer($this->client, validCustomer());
    $body = postCustomer($this->client, validCustomer(['email' => 'JANE.DOE@example.com']));

    expect($this->client->getResponse()->getStatusCode())->toBe(409)
        ->and($this->client->getResponse()->headers->get('Content-Type'))->toBe('application/json')
        ->and($body)->toBe([
            'status' => 409,
            'message' => "The email 'JANE.DOE@example.com' is already taken.",
            'errors' => ['email' => 'This email is already taken.'],
        ]);
});

it('rejects invalid fields with 422', function (array $overrides, string $field) {
    $body = postCustomer($this->client, validCustomer($overrides));

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and($this->client->getResponse()->headers->get('Content-Type'))->toBe('application/json')
        ->and($body)->toMatchArray([
            'status' => 422,
            'message' => 'The request contains invalid fields.',
        ])
        ->and($body['errors'])->toHaveKey($field)
        ->and($body)->not->toHaveKeys(['class', 'trace']);
})->with([
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'blank first name' => [['firstName' => ''], 'firstName'],
    'too long last name' => [['lastName' => str_repeat('a', 101)], 'lastName'],
    'unknown gender' => [['gender' => 'unknown'], 'gender'],
    'unknown country' => [['country' => 'XX'], 'country'],
    'lowercase country' => [['country' => 'de'], 'country'],
]);

it('reports every invalid field at once', function () {
    $body = postCustomer($this->client, validCustomer(['email' => 'not-an-email', 'gender' => 'unknown', 'country' => 'XX']));

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and(array_keys($body['errors']))->toEqualCanonicalizing(['email', 'gender', 'country']);
});

it('reports only the first failed check of a field', function () {
    $body = postCustomer($this->client, validCustomer(['email' => str_repeat('a', 300), 'gender' => '']));

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and($body['errors'])->toBe([
            'email' => 'This value is not a valid email address.',
            'gender' => 'This value should not be blank.',
        ]);
});

it('reports every missing field as blank', function () {
    $body = postCustomer($this->client, ['email' => 'jane.doe@example.com']);

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and($body['errors'])->toBe([
            'firstName' => 'This value should not be blank.',
            'lastName' => 'This value should not be blank.',
            'gender' => 'This value should not be blank.',
            'country' => 'This value should not be blank.',
        ]);
});

it('rejects a null field as the wrong type', function () {
    $body = postCustomer($this->client, validCustomer(['firstName' => null]));

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and($body['errors'])->toBe(['firstName' => 'This value should be of type string.']);
});

it('rejects a field of the wrong type', function () {
    $body = postCustomer($this->client, validCustomer(['country' => 123]));

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and($body['errors'])->toBe(['country' => 'This value should be of type string.']);
});

it('rejects malformed JSON with 400', function () {
    $body = postCustomer($this->client, '{"email":');

    expect($this->client->getResponse()->getStatusCode())->toBe(400)
        ->and($this->client->getResponse()->headers->get('Content-Type'))->toBe('application/json')
        ->and($body)->toBe([
            'status' => 400,
            'message' => "Request payload contains invalid 'json' data.",
            'errors' => [],
        ]);
});

it('rejects a body that is not JSON with 415', function () {
    // BrowserKit sends POST bodies as form data unless told otherwise
    $this->client->request('POST', '/api/customers', validCustomer());
    $body = json_decode($this->client->getResponse()->getContent(), true);

    expect($this->client->getResponse()->getStatusCode())->toBe(415)
        ->and($this->client->getResponse()->headers->get('Content-Type'))->toBe('application/json')
        ->and($body['message'])->toBe("Unsupported format, expects 'json', but 'form' given.");
});

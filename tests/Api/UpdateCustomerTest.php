<?php

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

beforeEach(function () {
    $this->client = self::createClient();
    $this->db = self::getContainer()->get(App\Database\Connection::class);
    $this->db->execute('DELETE FROM customers');
    $this->customer = postCustomer($this->client, validCustomer());
});

function patchCustomer(KernelBrowser $client, string $id, array|string $payload): array
{
    $client->request(
        'PATCH',
        '/api/customers/'.$id,
        server: ['CONTENT_TYPE' => 'application/json'],
        content: is_string($payload) ? $payload : json_encode($payload),
    );

    return json_decode($client->getResponse()->getContent(), true) ?? [];
}

it('updates only the fields that are sent', function () {
    $body = patchCustomer($this->client, $this->customer['id'], ['firstName' => 'Janet', 'country' => 'MT']);

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($body)->toMatchArray([
            'id' => $this->customer['id'],
            'email' => 'jane.doe@example.com',
            'firstName' => 'Janet',
            'lastName' => 'Doe',
            'gender' => 'female',
            'country' => 'MT',
            'bonusPercent' => $this->customer['bonusPercent'],
            'realBalance' => '0.00',
            'bonusBalance' => '0.00',
            'createdAt' => $this->customer['createdAt'],
        ])
        ->and($body['updatedAt'])->toBeGreaterThan($this->customer['updatedAt']);
});

it('stores the changes', function () {
    patchCustomer($this->client, $this->customer['id'], ['email' => 'janet@example.com', 'gender' => 'other']);

    $row = $this->db->fetchOne('SELECT email, gender FROM customers WHERE id = UUID_TO_BIN(?)', [$this->customer['id']]);

    expect($row)->toBe(['email' => 'janet@example.com', 'gender' => 'other']);
});

it('ignores read-only fields', function () {
    $body = patchCustomer($this->client, $this->customer['id'], [
        'lastName' => 'Smith',
        'bonusPercent' => 99,
        'realBalance' => '1000.00',
    ]);

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($body['bonusPercent'])->toBe($this->customer['bonusPercent'])
        ->and($body['realBalance'])->toBe('0.00');
});

it('lets a customer change the case of their own email', function () {
    $body = patchCustomer($this->client, $this->customer['id'], ['email' => 'Jane.Doe@example.com']);

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($body['email'])->toBe('Jane.Doe@example.com');
});

it('rejects an email taken by another customer, ignoring case', function () {
    postCustomer($this->client, validCustomer(['email' => 'john.doe@example.com']));

    $body = patchCustomer($this->client, $this->customer['id'], ['email' => 'JOHN.DOE@example.com']);

    expect($this->client->getResponse()->getStatusCode())->toBe(409)
        ->and($body)->toBe([
            'status' => 409,
            'message' => "The email 'JOHN.DOE@example.com' is already taken.",
            'errors' => ['email' => 'This email is already taken.'],
        ]);
});

it('returns 404 for an unknown customer', function () {
    $body = patchCustomer($this->client, '019a5a00-0000-7000-8000-000000000001', ['firstName' => 'Janet']);

    expect($this->client->getResponse()->getStatusCode())->toBe(404)
        ->and($this->client->getResponse()->headers->get('Content-Type'))->toBe('application/json')
        ->and($body)->toBe([
            'status' => 404,
            'message' => "Customer '019a5a00-0000-7000-8000-000000000001' not found.",
            'errors' => [],
        ]);
});

it('returns 404 for an id that is not a UUID', function () {
    patchCustomer($this->client, 'not-a-uuid', ['firstName' => 'Janet']);

    expect($this->client->getResponse()->getStatusCode())->toBe(404);
});

it('rejects invalid fields with 422', function (array $payload, string $field) {
    $body = patchCustomer($this->client, $this->customer['id'], $payload);

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and($body['message'])->toBe('The request contains invalid fields.')
        ->and($body['errors'])->toHaveKey($field);
})->with([
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'blank first name' => [['firstName' => ''], 'firstName'],
    'too long last name' => [['lastName' => str_repeat('a', 101)], 'lastName'],
    'unknown gender' => [['gender' => 'unknown'], 'gender'],
    'unknown country' => [['country' => 'XX'], 'country'],
]);

it('rejects a field sent as null', function () {
    $body = patchCustomer($this->client, $this->customer['id'], ['firstName' => null]);

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and($body['errors'])->toBe(['firstName' => 'This value should be of type string.']);
});

it('does not change anything when the request is invalid', function () {
    patchCustomer($this->client, $this->customer['id'], ['firstName' => 'Janet', 'country' => 'XX']);

    $row = $this->db->fetchOne('SELECT first_name FROM customers WHERE id = UUID_TO_BIN(?)', [$this->customer['id']]);

    expect($row)->toBe(['first_name' => 'Jane']);
});

it('rejects an empty body with 422', function (string $payload) {
    $body = patchCustomer($this->client, $this->customer['id'], $payload);

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and($body)->toBe([
            'status' => 422,
            'message' => 'The request must contain at least one field to update.',
            'errors' => [],
        ]);
})->with([
    'empty object' => ['{}'],
    'only read-only fields' => ['{"bonusPercent":10}'],
]);

it('rejects malformed JSON with 400', function () {
    patchCustomer($this->client, $this->customer['id'], '{"email":');

    expect($this->client->getResponse()->getStatusCode())->toBe(400);
});

<?php

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

beforeEach(function () {
    $this->client = self::createClient();
    $this->db = self::getContainer()->get(App\Database\Connection::class);
    resetDatabase($this->db);
    $this->customer = postCustomer($this->client, validCustomer());
    // a known percent makes the bonus predictable
    $this->db->execute('UPDATE customers SET bonus_percent = 10 WHERE id = UUID_TO_BIN(?)', [$this->customer['id']]);
});

function postDeposit(KernelBrowser $client, string $id, array|string $payload): array
{
    $client->request(
        'POST',
        '/api/customers/'.$id.'/deposits',
        server: ['CONTENT_TYPE' => 'application/json'],
        content: is_string($payload) ? $payload : json_encode($payload),
    );

    return json_decode($client->getResponse()->getContent(), true) ?? [];
}

it('deposits money into the real balance', function () {
    $body = postDeposit($this->client, $this->customer['id'], ['amount' => '100.50']);

    expect($this->client->getResponse()->getStatusCode())->toBe(201)
        ->and($body)->toHaveKeys(['transactionId', 'createdAt'])
        ->and($body['transactionId'])->toBeInt()
        ->and($body)->toMatchArray([
            'customerId' => $this->customer['id'],
            'amount' => '100.50',
            'bonusAmount' => '0.00',
            'realBalance' => '100.50',
            'bonusBalance' => '0.00',
        ])
        ->and(array_keys($body))->toBe(['transactionId', 'customerId', 'amount', 'bonusAmount', 'realBalance', 'bonusBalance', 'createdAt'])
        ->and($body['createdAt'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}\+00:00$/');
});

it('stores the new balances on the customer', function () {
    postDeposit($this->client, $this->customer['id'], ['amount' => '100.50']);
    postDeposit($this->client, $this->customer['id'], ['amount' => '20.00']);

    $this->client->request('GET', '/api/customers/'.$this->customer['id']);
    $customer = json_decode($this->client->getResponse()->getContent(), true);

    expect($customer['realBalance'])->toBe('120.50')
        ->and($customer['bonusBalance'])->toBe('0.00')
        ->and($customer['updatedAt'])->toBeGreaterThan($this->customer['updatedAt']);
});

it('records the deposit in the ledger with the country at that time', function () {
    $body = postDeposit($this->client, $this->customer['id'], ['amount' => '100.50']);

    $row = $this->db->fetchOne(
        'SELECT BIN_TO_UUID(customer_id) AS customer_id, type, amount, bonus_amount, country FROM transactions WHERE id = ?',
        [$body['transactionId']],
    );

    expect($row)->toBe([
        'customer_id' => $this->customer['id'],
        'type' => 'deposit',
        'amount' => '100.50',
        'bonus_amount' => '0.00',
        'country' => 'DE',
    ]);
});

it('credits a bonus on every 3rd deposit', function () {
    $bodies = array_map(
        fn (string $amount): array => postDeposit($this->client, $this->customer['id'], ['amount' => $amount]),
        ['10.00', '20.00', '100.00', '10.00', '20.00', '50.00'],
    );

    expect(array_column($bodies, 'bonusAmount'))->toBe(['0.00', '0.00', '10.00', '0.00', '0.00', '5.00'])
        ->and($bodies[2])->toMatchArray(['realBalance' => '130.00', 'bonusBalance' => '10.00'])
        ->and($bodies[5])->toMatchArray(['realBalance' => '210.00', 'bonusBalance' => '15.00']);
});

it('rounds the bonus half up to the cent', function () {
    $this->db->execute('UPDATE customers SET bonus_percent = 7 WHERE id = UUID_TO_BIN(?)', [$this->customer['id']]);

    postDeposit($this->client, $this->customer['id'], ['amount' => '1.00']);
    postDeposit($this->client, $this->customer['id'], ['amount' => '1.00']);
    // 12.50 × 7% = 0.875
    $body = postDeposit($this->client, $this->customer['id'], ['amount' => '12.50']);

    expect($body)->toMatchArray(['bonusAmount' => '0.88', 'bonusBalance' => '0.88']);
});

it('does not count a rejected deposit toward the bonus', function () {
    postDeposit($this->client, $this->customer['id'], ['amount' => '10.00']);
    postDeposit($this->client, $this->customer['id'], ['amount' => '0.00']);
    postDeposit($this->client, $this->customer['id'], ['amount' => '10.00']);
    $body = postDeposit($this->client, $this->customer['id'], ['amount' => '10.00']);

    expect($body['bonusAmount'])->toBe('1.00');
});

it('accepts the maximum amount', function () {
    $body = postDeposit($this->client, $this->customer['id'], ['amount' => '10000.00']);

    expect($this->client->getResponse()->getStatusCode())->toBe(201)
        ->and($body['realBalance'])->toBe('10000.00');
});

it('formats amounts with two decimals', function (string $amount, string $expected) {
    $body = postDeposit($this->client, $this->customer['id'], ['amount' => $amount]);

    expect($this->client->getResponse()->getStatusCode())->toBe(201)
        ->and($body['amount'])->toBe($expected);
})->with([
    'no decimals' => ['25', '25.00'],
    'one decimal' => ['25.5', '25.50'],
]);

it('rejects an invalid amount with 422', function (mixed $amount, string $message) {
    $body = postDeposit($this->client, $this->customer['id'], ['amount' => $amount]);

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and($body)->toBe([
            'status' => 422,
            'message' => 'The request contains invalid fields.',
            'errors' => ['amount' => $message],
        ]);
})->with([
    'zero' => ['0.00', 'This value should be positive.'],
    'negative' => ['-10.00', 'This value must be an amount with at most two decimals.'],
    'three decimals' => ['10.001', 'This value must be an amount with at most two decimals.'],
    'not a number' => ['ten', 'This value must be an amount with at most two decimals.'],
    'exponent' => ['1e3', 'This value must be an amount with at most two decimals.'],
    'blank' => ['', 'This value should not be blank.'],
    'above the maximum' => ['10000.01', 'This value should be less than or equal to 10000.'],
    'JSON number' => [100.5, 'This value should be of type string.'],
    'JSON integer' => [100, 'This value should be of type string.'],
    'null' => [null, 'This value should be of type string.'],
]);

it('rejects a missing amount with 422', function () {
    $body = postDeposit($this->client, $this->customer['id'], '{}');

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and($body['errors'])->toBe(['amount' => 'This value should not be blank.']);
});

it('does not change anything when the deposit is rejected', function () {
    postDeposit($this->client, $this->customer['id'], ['amount' => '10000.01']);

    $count = $this->db->fetchOne('SELECT COUNT(*) AS count FROM transactions');
    $row = $this->db->fetchOne('SELECT real_balance FROM customers WHERE id = UUID_TO_BIN(?)', [$this->customer['id']]);

    expect($count)->toBe(['count' => 0])
        ->and($row)->toBe(['real_balance' => '0.00']);
});

it('returns 404 for an unknown customer', function () {
    $body = postDeposit($this->client, '019a5a00-0000-7000-8000-000000000001', ['amount' => '10.00']);

    expect($this->client->getResponse()->getStatusCode())->toBe(404)
        ->and($body)->toBe([
            'status' => 404,
            'message' => "Customer '019a5a00-0000-7000-8000-000000000001' not found.",
            'errors' => [],
        ]);
});

it('rejects malformed JSON with 400', function () {
    postDeposit($this->client, $this->customer['id'], '{"amount":');

    expect($this->client->getResponse()->getStatusCode())->toBe(400);
});

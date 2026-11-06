<?php

declare(strict_types=1);

use Symfony\Bundle\FrameworkBundle\KernelBrowser;

beforeEach(function () {
    $this->client = self::createClient();
    $this->db = self::getContainer()->get(App\Database\Connection::class);
    resetDatabase($this->db);
    $this->customer = postCustomer($this->client, validCustomer());
    $this->db->execute(
        'UPDATE customers SET real_balance = 100.00, bonus_balance = 10.00 WHERE id = UUID_TO_BIN(?)',
        [$this->customer['id']],
    );
});

function postWithdrawal(KernelBrowser $client, string $id, array|string $payload): array
{
    $client->request(
        'POST',
        '/api/customers/'.$id.'/withdrawals',
        server: ['CONTENT_TYPE' => 'application/json'],
        content: is_string($payload) ? $payload : json_encode($payload),
    );

    return json_decode($client->getResponse()->getContent(), true) ?? [];
}

it('withdraws money from the real balance', function () {
    $body = postWithdrawal($this->client, $this->customer['id'], ['amount' => '40.25']);

    expect($this->client->getResponse()->getStatusCode())->toBe(201)
        ->and($body)->toHaveKeys(['transactionId', 'createdAt'])
        ->and($body['transactionId'])->toBeInt()
        ->and($body)->toMatchArray([
            'customerId' => $this->customer['id'],
            'amount' => '40.25',
            'realBalance' => '59.75',
            'bonusBalance' => '10.00',
        ])
        ->and(array_keys($body))->toBe(['transactionId', 'customerId', 'amount', 'realBalance', 'bonusBalance', 'createdAt'])
        ->and($body['createdAt'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{3}\+00:00$/');
});

it('stores the new balances on the customer', function () {
    postWithdrawal($this->client, $this->customer['id'], ['amount' => '40.25']);
    postWithdrawal($this->client, $this->customer['id'], ['amount' => '9.75']);

    $this->client->request('GET', '/api/customers/'.$this->customer['id']);
    $customer = json_decode($this->client->getResponse()->getContent(), true);

    expect($customer['realBalance'])->toBe('50.00')
        ->and($customer['bonusBalance'])->toBe('10.00')
        ->and($customer['updatedAt'])->toBeGreaterThan($this->customer['updatedAt']);
});

it('records the withdrawal in the ledger with the country at that time', function () {
    $body = postWithdrawal($this->client, $this->customer['id'], ['amount' => '40.25']);

    $row = $this->db->fetchOne(
        'SELECT BIN_TO_UUID(customer_id) AS customer_id, type, amount, bonus_amount, country FROM transactions WHERE id = ?',
        [$body['transactionId']],
    );

    expect($row)->toBe([
        'customer_id' => $this->customer['id'],
        'type' => 'withdrawal',
        'amount' => '40.25',
        'bonus_amount' => '0.00',
        'country' => 'DE',
    ]);
});

it('withdraws the whole real balance', function () {
    $body = postWithdrawal($this->client, $this->customer['id'], ['amount' => '100.00']);

    expect($this->client->getResponse()->getStatusCode())->toBe(201)
        ->and($body)->toMatchArray(['realBalance' => '0.00', 'bonusBalance' => '10.00']);
});

it('rejects a withdrawal above the real balance with 422', function () {
    // 100.00 real + 10.00 bonus: the bonus is never withdrawable
    $body = postWithdrawal($this->client, $this->customer['id'], ['amount' => '100.01']);

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and($body)->toBe([
            'status' => 422,
            'message' => "Insufficient funds: the withdrawable balance is '100.00'.",
            'errors' => ['amount' => 'This value exceeds the withdrawable balance.'],
        ]);
});

it('does not change anything when the withdrawal is rejected', function () {
    postWithdrawal($this->client, $this->customer['id'], ['amount' => '100.01']);

    $count = $this->db->fetchOne('SELECT COUNT(*) AS count FROM transactions');
    $row = $this->db->fetchOne('SELECT real_balance, bonus_balance FROM customers WHERE id = UUID_TO_BIN(?)', [$this->customer['id']]);

    expect($count)->toBe(['count' => 0])
        ->and($row)->toBe(['real_balance' => '100.00', 'bonus_balance' => '10.00']);
});

it('does not count withdrawals toward the deposit bonus', function () {
    $this->db->execute('UPDATE customers SET bonus_percent = 10 WHERE id = UUID_TO_BIN(?)', [$this->customer['id']]);

    postDeposit($this->client, $this->customer['id'], ['amount' => '10.00']);
    postWithdrawal($this->client, $this->customer['id'], ['amount' => '10.00']);
    postDeposit($this->client, $this->customer['id'], ['amount' => '10.00']);
    $body = postDeposit($this->client, $this->customer['id'], ['amount' => '10.00']);

    expect($body['bonusAmount'])->toBe('1.00');
});

it('rejects an invalid amount with 422', function (mixed $amount, string $message) {
    $body = postWithdrawal($this->client, $this->customer['id'], ['amount' => $amount]);

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
    'blank' => ['', 'This value should not be blank.'],
    'above the maximum' => ['10000.01', 'This value should be less than or equal to 10000.'],
    'JSON number' => [10.5, 'This value should be of type string.'],
    'null' => [null, 'This value should be of type string.'],
]);

it('rejects a missing amount with 422', function () {
    $body = postWithdrawal($this->client, $this->customer['id'], '{}');

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and($body['errors'])->toBe(['amount' => 'This value should not be blank.']);
});

it('returns 404 for an unknown customer', function () {
    $body = postWithdrawal($this->client, '019a5a00-0000-7000-8000-000000000001', ['amount' => '10.00']);

    expect($this->client->getResponse()->getStatusCode())->toBe(404)
        ->and($body)->toBe([
            'status' => 404,
            'message' => "Customer '019a5a00-0000-7000-8000-000000000001' not found.",
            'errors' => [],
        ]);
});

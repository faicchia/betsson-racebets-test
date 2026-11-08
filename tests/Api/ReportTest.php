<?php

declare(strict_types=1);

use App\Database\Connection;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

uses(ClockSensitiveTrait::class);

beforeEach(function () {
    $this->client = self::createClient();
    $this->db = self::getContainer()->get(Connection::class);
    resetDatabase($this->db);
    $this->jane = postCustomer($this->client, validCustomer())['id'];
    $this->john = postCustomer($this->client, validCustomer(['email' => 'john.doe@example.com', 'firstName' => 'John']))['id'];
});

/**
 * Inserts a ledger row directly, so the test controls its date and country snapshot.
 */
function insertTransaction(Connection $db, string $customerId, string $type, string $amount, string $country, string $createdAt): void
{
    $db->execute(
        'INSERT INTO transactions (customer_id, type, amount, country, created_at) VALUES (UUID_TO_BIN(?), ?, ?, ?, ?)',
        [$customerId, $type, $amount, $country, $createdAt],
    );
}

function getReport(Symfony\Bundle\FrameworkBundle\KernelBrowser $client, array $query = []): array
{
    $client->request('GET', '/api/reports/daily-transaction-totals'.([] === $query ? '' : '?'.http_build_query($query)));

    return json_decode($client->getResponse()->getContent(), true) ?? [];
}

it('aggregates deposits and withdrawals per date and country', function () {
    insertTransaction($this->db, $this->jane, 'deposit', '100.00', 'DE', '2015-05-06 08:00:00');
    insertTransaction($this->db, $this->jane, 'deposit', '50.25', 'DE', '2015-05-06 09:00:00');
    insertTransaction($this->db, $this->jane, 'withdrawal', '30.00', 'DE', '2015-05-06 10:00:00');
    insertTransaction($this->db, $this->john, 'withdrawal', '20.45', 'DE', '2015-05-06 23:59:59.999999');
    insertTransaction($this->db, $this->john, 'deposit', '10.00', 'MT', '2015-05-06 12:00:00');

    $body = getReport($this->client, ['from' => '2015-05-06', 'to' => '2015-05-06']);

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($body)->toBe([
            'from' => '2015-05-06',
            'to' => '2015-05-06',
            'data' => [
                [
                    'date' => '2015-05-06',
                    'country' => 'DE',
                    'uniqueCustomers' => 2,
                    'depositCount' => 2,
                    'depositTotal' => '150.25',
                    'withdrawalCount' => 2,
                    'withdrawalTotal' => '-50.45',
                ],
                [
                    'date' => '2015-05-06',
                    'country' => 'MT',
                    'uniqueCustomers' => 1,
                    'depositCount' => 1,
                    'depositTotal' => '10.00',
                    'withdrawalCount' => 0,
                    'withdrawalTotal' => '0.00',
                ],
            ],
        ]);
});

it('sorts entries by date descending, then country ascending', function () {
    insertTransaction($this->db, $this->jane, 'deposit', '1.00', 'MT', '2015-05-05 12:00:00');
    insertTransaction($this->db, $this->jane, 'deposit', '1.00', 'MT', '2015-05-06 12:00:00');
    insertTransaction($this->db, $this->jane, 'deposit', '1.00', 'DE', '2015-05-05 12:00:00');
    insertTransaction($this->db, $this->jane, 'deposit', '1.00', 'DE', '2015-05-06 12:00:00');

    $body = getReport($this->client, ['from' => '2015-05-05', 'to' => '2015-05-06']);

    expect(array_map(fn (array $row): string => $row['date'].' '.$row['country'], $body['data']))
        ->toBe(['2015-05-06 DE', '2015-05-06 MT', '2015-05-05 DE', '2015-05-05 MT']);
});

it('includes both ends of the range and nothing outside it', function () {
    insertTransaction($this->db, $this->jane, 'deposit', '1.00', 'DE', '2015-05-03 23:59:59.999999');
    insertTransaction($this->db, $this->jane, 'deposit', '2.00', 'DE', '2015-05-04 00:00:00');
    insertTransaction($this->db, $this->jane, 'deposit', '3.00', 'DE', '2015-05-06 23:59:59.999999');
    insertTransaction($this->db, $this->jane, 'deposit', '4.00', 'DE', '2015-05-07 00:00:00');

    $body = getReport($this->client, ['from' => '2015-05-04', 'to' => '2015-05-06']);

    expect(array_column($body['data'], 'depositTotal', 'date'))->toBe(['2015-05-06' => '3.00', '2015-05-04' => '2.00']);
});

it('groups by the country at the time of the transaction', function () {
    postDeposit($this->client, $this->jane, ['amount' => '10.00']);
    $this->client->request('PATCH', '/api/customers/'.$this->jane, server: ['CONTENT_TYPE' => 'application/json'], content: json_encode(['country' => 'MT']));
    postDeposit($this->client, $this->jane, ['amount' => '20.00']);

    $body = getReport($this->client);

    expect(array_column($body['data'], 'depositTotal', 'country'))->toBe(['DE' => '10.00', 'MT' => '20.00']);
});

it('defaults to the last 7 days including today', function () {
    $today = new DateTimeImmutable('2015-05-10', new DateTimeZone('UTC'));
    self::mockTime($today);
    insertTransaction($this->db, $this->jane, 'deposit', '1.00', 'DE', $today->modify('-7 days')->format('Y-m-d 12:00:00'));
    insertTransaction($this->db, $this->jane, 'deposit', '2.00', 'DE', $today->modify('-6 days')->format('Y-m-d 00:00:00'));
    insertTransaction($this->db, $this->jane, 'deposit', '3.00', 'DE', $today->format('Y-m-d 12:00:00'));

    $body = getReport($this->client);

    expect($body['from'])->toBe($today->modify('-6 days')->format('Y-m-d'))
        ->and($body['to'])->toBe($today->format('Y-m-d'))
        ->and(array_column($body['data'], 'depositTotal'))->toBe(['3.00', '2.00']);
});

it('defaults the start to 6 days before a given end', function () {
    $body = getReport($this->client, ['to' => '2015-05-06']);

    expect($body)->toMatchArray(['from' => '2015-04-30', 'to' => '2015-05-06']);
});

it('defaults the end to today when only the start is given', function () {
    $today = new DateTimeImmutable('2015-05-10', new DateTimeZone('UTC'));
    self::mockTime($today);
    $from = $today->modify('-10 days')->format('Y-m-d');

    $body = getReport($this->client, ['from' => $from]);

    expect($body)->toMatchArray(['from' => $from, 'to' => $today->format('Y-m-d')]);
});

it('takes today in UTC, not in the server timezone', function () {
    // 00:30 in Rome on the 10th is still the 9th in UTC
    self::mockTime(new DateTimeImmutable('2015-05-10 00:30:00', new DateTimeZone('Europe/Rome')));

    $body = getReport($this->client);

    expect($body['to'])->toBe('2015-05-09');
});

it('accepts a range of up to 3 months', function (string $from, string $to) {
    getReport($this->client, ['from' => $from, 'to' => $to]);

    expect($this->client->getResponse()->getStatusCode())->toBe(200);
})->with([
    'from the 1st' => ['2015-01-01', '2015-03-31'],
    'from mid-month' => ['2015-05-06', '2015-08-05'],
]);

it('returns no entries for a range without transactions', function () {
    $body = getReport($this->client, ['from' => '2015-05-06', 'to' => '2015-05-06']);

    expect($this->client->getResponse()->getStatusCode())->toBe(200)
        ->and($body['data'])->toBe([]);
});

it('rejects an invalid range with 422', function (array $query, array $errors) {
    $body = getReport($this->client, $query);

    expect($this->client->getResponse()->getStatusCode())->toBe(422)
        ->and($body)->toBe([
            'status' => 422,
            'message' => 'The request contains invalid fields.',
            'errors' => $errors,
        ]);
})->with([
    'not a date' => [['from' => 'yesterday'], ['from' => 'This value is not a valid date.']],
    'impossible date' => [['to' => '2015-02-30'], ['to' => 'This value is not a valid date.']],
    'wrong format' => [['from' => '06.05.2015'], ['from' => 'This value is not a valid date.']],
    'blank' => [['from' => ''], ['from' => 'This value should not be blank.']],
    'start after end' => [['from' => '2015-05-07', 'to' => '2015-05-06'], ['from' => 'This value must not be after the end of the range.']],
    'longer than 3 months' => [['from' => '2015-05-06', 'to' => '2015-08-06'], ['from' => 'This value must be less than 3 months before the end of the range.']],
    'only the start, too far back' => [['from' => '2015-05-06'], ['from' => 'This value must be less than 3 months before the end of the range.']],
]);

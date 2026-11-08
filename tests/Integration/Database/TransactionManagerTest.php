<?php

declare(strict_types=1);

use App\Database\Connection;
use App\Database\TransactionManager;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

uses(ClockSensitiveTrait::class);

beforeEach(function () {
    $this->db = self::getContainer()->get(Connection::class);
    $this->transactions = self::getContainer()->get(TransactionManager::class);
    resetDatabase($this->db);
    // the backoff sleeps on the mock clock, which only moves its time forward
    $this->start = new DateTimeImmutable('2015-05-10 12:00:00', new DateTimeZone('UTC'));
    $this->clock = self::mockTime($this->start);
});

function insertCustomerRow(Connection $db, string $email): void
{
    $db->execute(
        <<<'SQL'
            INSERT INTO customers (id, email, first_name, last_name, gender, country, bonus_percent, created_at, updated_at)
            VALUES (UUID_TO_BIN(UUID()), ?, 'Jane', 'Doe', 'female', 'DE', 10, UTC_TIMESTAMP(6), UTC_TIMESTAMP(6))
            SQL,
        [$email],
    );
}

function countCustomers(Connection $db): int
{
    return $db->fetchOne('SELECT COUNT(*) AS n FROM customers')['n'];
}

function mysqlError(int $code): PDOException
{
    $e = new PDOException('SQLSTATE[40001]: simulated MySQL error '.$code);
    $e->errorInfo = ['40001', $code, 'simulated'];

    return $e;
}

it('commits the work and returns the result of the callback', function () {
    $result = $this->transactions->transactional(function () {
        insertCustomerRow($this->db, 'jane@example.com');

        return 'done';
    });

    expect($result)->toBe('done')
        ->and(countCustomers($this->db))->toBe(1);
});

it('rolls back the work and rethrows when the callback fails', function () {
    $run = fn () => $this->transactions->transactional(function () {
        insertCustomerRow($this->db, 'jane@example.com');

        throw new RuntimeException('boom');
    });

    expect($run)->toThrow(RuntimeException::class, 'boom')
        ->and(countCustomers($this->db))->toBe(0);
});

it('retries the whole callback after a deadlock or a lock wait timeout', function (int $code) {
    $attempts = 0;

    $result = $this->transactions->transactional(function () use (&$attempts, $code) {
        insertCustomerRow($this->db, 'attempt'.++$attempts.'@example.com');
        if ($attempts < 3) {
            throw mysqlError($code);
        }

        return $attempts;
    });

    // the rows of the failed attempts were rolled back
    expect($result)->toBe(3)
        ->and($this->db->fetchOne('SELECT email FROM customers'))->toBe(['email' => 'attempt3@example.com']);
})->with([
    'deadlock' => [1213],
    'lock wait timeout' => [1205],
]);

it('backs off exponentially, with jitter, between attempts', function () {
    $attempts = 0;

    $this->transactions->transactional(function () use (&$attempts) {
        if (++$attempts < 3) {
            throw mysqlError(1213);
        }
    });

    // 50ms then 100ms, each plus up to 10ms of jitter
    expect($this->clock->now())
        ->toBeGreaterThanOrEqual($this->start->modify('+150 milliseconds'))
        ->toBeLessThanOrEqual($this->start->modify('+170 milliseconds'));
});

it('gives up after the last attempt', function () {
    $attempts = 0;

    $run = function () use (&$attempts) {
        $this->transactions->transactional(function () use (&$attempts) {
            ++$attempts;

            throw mysqlError(1213);
        });
    };

    expect($run)->toThrow(PDOException::class)
        ->and($attempts)->toBe(3);
});

it('does not retry other database errors', function () {
    $attempts = 0;

    $run = function () use (&$attempts) {
        $this->transactions->transactional(function () use (&$attempts) {
            ++$attempts;

            throw mysqlError(1062);
        });
    };

    expect($run)->toThrow(PDOException::class)
        ->and($attempts)->toBe(1);
});

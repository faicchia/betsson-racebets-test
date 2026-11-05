<?php

declare(strict_types=1);

namespace App\Database;

use LogicException;
use PDOException;
use Throwable;

/**
 * Runs a unit of work in one DB transaction and retries it when MySQL aborts it on a lock conflict.
 */
final readonly class TransactionManager
{
    private const int MAX_ATTEMPTS = 3;
    private const int MYSQL_DEADLOCK = 1213;
    private const int MYSQL_LOCK_WAIT_TIMEOUT = 1205;
    private const int BASE_BACKOFF_MICROSECONDS = 50_000; // 50ms base

    public function __construct(
        private Connection $connection,
    ) {
    }

    /**
     * The callback may run more than once, so it must not have side effects outside the DB.
     *
     * @template T
     *
     * @param callable(): T $work
     *
     * @return T
     */
    public function transactional(callable $work): mixed
    {
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            $this->connection->beginTransaction();

            try {
                $result = $work();
                $this->connection->commit();

                return $result;
            } catch (Throwable $e) {
                // A deadlock rolls back on the server automatically,
                // but a lock wait timeout only rolls back the statement.
                if ($this->connection->inTransaction()) {
                    $this->connection->rollBack();
                }

                if (!self::isLockConflict($e) || self::MAX_ATTEMPTS === $attempt) {
                    throw $e;
                }

                // Exponential backoff with random jitter to prevent thundering herd conflicts
                $backoff = self::BASE_BACKOFF_MICROSECONDS * (2 ** ($attempt - 1));
                $jitter = random_int(0, 10_000); // Add up to 10ms of random jitter

                usleep($backoff + $jitter);
            }
        }

        // This point is technically unreachable due to the exception throw above,
        // but it satisfies static analyzers that expect a return.
        throw new LogicException('Unreachable transaction state.');
    }

    private static function isLockConflict(Throwable $e): bool
    {
        return $e instanceof PDOException
            && in_array($e->errorInfo[1] ?? null, [self::MYSQL_DEADLOCK, self::MYSQL_LOCK_WAIT_TIMEOUT], true);
    }
}

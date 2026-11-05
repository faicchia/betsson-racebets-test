<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;
use App\Exception\EmailAlreadyTaken;
use App\Model\Customer;
use PDOException;

final readonly class PdoCustomerRepository implements CustomerRepository
{
    private const int MYSQL_DUPLICATE_KEY = 1062;
    private const string DATETIME_FORMAT = 'Y-m-d H:i:s.u';

    public function __construct(
        private Connection $connection
    ) {
    }

    public function add(Customer $customer): void
    {
        try {
            $this->connection->execute(
                <<<'SQL'
                    INSERT INTO customers
                        (id, email, first_name, last_name, gender, country, bonus_percent, real_balance, bonus_balance, created_at, updated_at)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    SQL,
                    self::toParameters($customer),
            );
        } catch (PDOException $e) {
            if (self::isDuplicateKey($e, 'uq_customers_email')) {
                throw EmailAlreadyTaken::withEmail($customer->email);
            }

            throw $e;
        }
    }

    /**
     * @return list<mixed>
     */
    private static function toParameters(Customer $customer): array
    {
        return [
            $customer->id->toBinary(),
            $customer->email,
            $customer->firstName,
            $customer->lastName,
            $customer->gender->value,
            $customer->country,
            $customer->bonusPercent,
            $customer->realBalance->toString(),
            $customer->bonusBalance->toString(),
            $customer->createdAt->format(self::DATETIME_FORMAT),
            $customer->updatedAt->format(self::DATETIME_FORMAT),
        ];
    }

    private static function isDuplicateKey(PDOException $e, string $key): bool
    {
        return self::MYSQL_DUPLICATE_KEY === ($e->errorInfo[1] ?? null)
            && str_contains($e->getMessage(), $key);
    }
}

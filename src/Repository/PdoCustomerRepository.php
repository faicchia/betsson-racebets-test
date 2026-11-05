<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;
use App\Exception\EmailAlreadyTaken;
use App\Model\Customer;
use App\ValueObject\Gender;
use App\ValueObject\Money;
use DateTimeImmutable;
use DateTimeZone;
use PDOException;
use Symfony\Component\Uid\Uuid;

final readonly class PdoCustomerRepository implements CustomerRepository
{
    private const int MYSQL_DUPLICATE_KEY = 1062;
    private const string DATETIME_FORMAT = 'Y-m-d H:i:s.u';

    /**
     * @var array<'email'|'firstName'|'lastName'|'gender'|'country', string>
     */
    private const array FIELD_COLUMNS = [
        'email' => 'email',
        'firstName' => 'first_name',
        'lastName' => 'last_name',
        'gender' => 'gender',
        'country' => 'country',
    ];

    public function __construct(
        private Connection $connection,
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

    public function find(Uuid $id): ?Customer
    {
        $row = $this->connection->fetchOne(
            <<<'SQL'
                SELECT id, email, first_name, last_name, gender, country, bonus_percent, real_balance, bonus_balance, created_at, updated_at
                FROM customers
                WHERE id = ?
                SQL,
            [$id->toBinary()],
        );

        return null === $row ? null : self::toCustomer($row);
    }

    public function update(Uuid $id, array $fields): bool
    {
        $assignments = [];
        $parameters = [];
        foreach ($fields as $property => $value) {
            $assignments[] = self::FIELD_COLUMNS[$property].' = ?';
            $parameters[] = $value;
        }

        try {
            // updated_at always changes, so an existing row is always counted as affected
            return 1 === $this->connection->execute(
                sprintf('UPDATE customers SET %s, updated_at = ? WHERE id = ?', implode(', ', $assignments)),
                [...$parameters, (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(self::DATETIME_FORMAT), $id->toBinary()],
            );
        } catch (PDOException $e) {
            if (isset($fields['email']) && self::isDuplicateKey($e, 'uq_customers_email')) {
                throw EmailAlreadyTaken::withEmail($fields['email']);
            }

            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function toCustomer(array $row): Customer
    {
        /** @var array{id: string, email: string, first_name: string, last_name: string, gender: string, country: string, bonus_percent: int, real_balance: numeric-string, bonus_balance: numeric-string, created_at: string, updated_at: string} $row */
        $utc = new DateTimeZone('UTC');

        return new Customer(
            id: Uuid::fromBinary($row['id']),
            email: $row['email'],
            firstName: $row['first_name'],
            lastName: $row['last_name'],
            gender: Gender::from($row['gender']),
            country: $row['country'],
            bonusPercent: $row['bonus_percent'],
            realBalance: Money::of($row['real_balance']),
            bonusBalance: Money::of($row['bonus_balance']),
            createdAt: new DateTimeImmutable($row['created_at'], $utc),
            updatedAt: new DateTimeImmutable($row['updated_at'], $utc),
        );
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

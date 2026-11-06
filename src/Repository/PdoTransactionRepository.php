<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;
use App\Model\Customer;
use App\Model\Transaction;
use App\ValueObject\Money;
use App\ValueObject\TransactionType;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

final readonly class PdoTransactionRepository implements TransactionRepository
{
    private const string DATETIME_FORMAT = 'Y-m-d H:i:s.u';

    public function __construct(
        private Connection $connection,
    ) {
    }

    public function deposit(Customer $customer, Money $amount, Money $bonusAmount): Transaction
    {
        return $this->insert($customer, TransactionType::Deposit, $amount, $bonusAmount);
    }

    public function countDeposits(Uuid $customerId): int
    {
        /** @var array{count: int} $row */
        $row = $this->connection->fetchOne(
            'SELECT COUNT(*) AS count FROM transactions WHERE customer_id = ? AND type = ?',
            [$customerId->toBinary(), TransactionType::Deposit->value],
        );

        return $row['count'];
    }

    private function insert(Customer $customer, TransactionType $type, Money $amount, Money $bonusAmount): Transaction
    {
        $createdAt = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $this->connection->execute(
            <<<'SQL'
                INSERT INTO transactions
                    (customer_id, type, amount, bonus_amount, country, created_at)
                VALUES
                    (?, ?, ?, ?, ?, ?)
                SQL,
            self::toParameters($customer, $type, $amount, $bonusAmount, $createdAt),
        );

        return new Transaction(
            id: $this->connection->lastInsertId(),
            customerId: $customer->id,
            type: $type,
            amount: $amount,
            bonusAmount: $bonusAmount,
            country: $customer->country,
            createdAt: $createdAt,
        );
    }

    /**
     * @return list<mixed>
     */
    private static function toParameters(
        Customer $customer,
        TransactionType $type,
        Money $amount,
        Money $bonusAmount,
        DateTimeImmutable $createdAt,
    ): array {
        return [
            $customer->id->toBinary(),
            $type->value,
            $amount->toString(),
            $bonusAmount->toString(),
            $customer->country,
            $createdAt->format(self::DATETIME_FORMAT),
        ];
    }
}

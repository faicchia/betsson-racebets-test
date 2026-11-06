<?php

declare(strict_types=1);

namespace App\Repository;

use App\Database\Connection;
use App\Model\Customer;
use App\Model\ReportRow;
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

    public function withdraw(Customer $customer, Money $amount): Transaction
    {
        return $this->insert($customer, TransactionType::Withdrawal, $amount, Money::zero());
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

    public function dailyTotalsByCountry(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        // a half-open range on created_at, rather than DATE(created_at) BETWEEN, lets MySQL use the index
        /** @var list<array{day: string, country: string, unique_customers: int, deposit_count: int, deposit_total: numeric-string, withdrawal_count: int, withdrawal_total: numeric-string}> $rows */
        $rows = $this->connection->fetchAll(
            <<<'SQL'
                SELECT
                    DATE(created_at) AS day,
                    country,
                    COUNT(DISTINCT customer_id) AS unique_customers,
                    COUNT(CASE WHEN type = 'deposit' THEN 1 END) AS deposit_count,
                    SUM(CASE WHEN type = 'deposit' THEN amount ELSE 0 END) AS deposit_total,
                    COUNT(CASE WHEN type = 'withdrawal' THEN 1 END) AS withdrawal_count,
                    -SUM(CASE WHEN type = 'withdrawal' THEN amount ELSE 0 END) AS withdrawal_total
                FROM transactions
                WHERE created_at >= ? AND created_at < ?
                GROUP BY day, country
                ORDER BY day DESC, country ASC
                SQL,
            [
                $from->format(self::DATETIME_FORMAT),
                $to->modify('+1 day')->format(self::DATETIME_FORMAT),
            ],
        );

        return array_map(self::toReportRow(...), $rows);
    }

    /**
     * @param array{day: string, country: string, unique_customers: int, deposit_count: int, deposit_total: numeric-string, withdrawal_count: int, withdrawal_total: numeric-string} $row
     */
    private static function toReportRow(array $row): ReportRow
    {
        return new ReportRow(
            date: new DateTimeImmutable($row['day'], new DateTimeZone('UTC')),
            country: $row['country'],
            uniqueCustomers: $row['unique_customers'],
            depositCount: $row['deposit_count'],
            depositTotal: Money::of($row['deposit_total']),
            withdrawalCount: $row['withdrawal_count'],
            withdrawalTotal: Money::of($row['withdrawal_total']),
        );
    }
}

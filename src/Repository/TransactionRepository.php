<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Customer;
use App\Model\ReportRow;
use App\Model\Transaction;
use App\ValueObject\Money;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

interface TransactionRepository
{
    public function deposit(Customer $customer, Money $amount, Money $bonusAmount): Transaction;

    public function withdraw(Customer $customer, Money $amount): Transaction;

    public function countDeposits(Uuid $customerId): int;

    /**
     * @return list<ReportRow> only days and countries with at least one transaction
     */
    public function dailyTotalsByCountry(DateTimeImmutable $from, DateTimeImmutable $to): array;
}

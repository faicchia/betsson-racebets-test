<?php

declare(strict_types=1);

namespace App\Repository;

use App\Model\Customer;
use App\Model\Transaction;
use App\ValueObject\Money;
use Symfony\Component\Uid\Uuid;

interface TransactionRepository
{
    public function deposit(Customer $customer, Money $amount, Money $bonusAmount): Transaction;

    public function countDeposits(Uuid $customerId): int;
}

<?php

declare(strict_types=1);

namespace App\Model;

final readonly class BalanceUpdate
{
    public function __construct(
        public Transaction $transaction,
        public Customer $customer,
    ) {
    }
}

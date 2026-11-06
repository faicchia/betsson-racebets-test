<?php

declare(strict_types=1);

namespace App\ValueObject;

enum TransactionType: string
{
    case Deposit = 'deposit';
    case Withdrawal = 'withdrawal';
}

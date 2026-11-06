<?php

declare(strict_types=1);

namespace App\Exception;

use App\ValueObject\Money;
use RuntimeException;

final class InsufficientFunds extends RuntimeException
{
    public static function withWithdrawableBalance(Money $realBalance): self
    {
        return new self(sprintf("Insufficient funds: the withdrawable balance is '%s'.", $realBalance->toString()));
    }
}

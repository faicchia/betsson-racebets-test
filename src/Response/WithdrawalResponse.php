<?php

declare(strict_types=1);

namespace App\Response;

use App\Model\BalanceUpdate;
use DateTimeInterface;

final readonly class WithdrawalResponse
{
    private function __construct(
        public int $transactionId,
        public string $customerId,
        public string $amount,
        public string $realBalance,
        public string $bonusBalance,
        public string $createdAt,
    ) {
    }

    public static function fromBalanceUpdate(BalanceUpdate $update): self
    {
        return new self(
            transactionId: $update->transaction->id,
            customerId: $update->customer->id->toRfc4122(),
            amount: $update->transaction->amount->toString(),
            realBalance: $update->customer->realBalance->toString(),
            bonusBalance: $update->customer->bonusBalance->toString(),
            createdAt: $update->transaction->createdAt->format(DateTimeInterface::RFC3339_EXTENDED),
        );
    }
}

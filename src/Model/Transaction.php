<?php

declare(strict_types=1);

namespace App\Model;

use App\ValueObject\Money;
use App\ValueObject\TransactionType;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class Transaction
{
    public function __construct(
        public int $id,
        public Uuid $customerId,
        public TransactionType $type,
        public Money $amount,
        public Money $bonusAmount,
        public string $country,
        public DateTimeImmutable $createdAt,
    ) {
    }
}

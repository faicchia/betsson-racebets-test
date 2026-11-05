<?php

declare(strict_types=1);

namespace App\Model;

use App\ValueObject\Gender;
use App\ValueObject\Money;
use DateTimeImmutable;
use Symfony\Component\Uid\Uuid;

final readonly class Customer
{
    public function __construct(
        public Uuid $id,
        public string $email,
        public string $firstName,
        public string $lastName,
        public Gender $gender,
        public string $country,
        public int $bonusPercent,
        public Money $realBalance,
        public Money $bonusBalance,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Response;

use App\Model\Customer;
use DateTimeInterface;

final readonly class CustomerResponse
{
    private function __construct(
        public string $id,
        public string $email,
        public string $firstName,
        public string $lastName,
        public string $gender,
        public string $country,
        public int $bonusPercent,
        public string $realBalance,
        public string $bonusBalance,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromCustomer(Customer $customer): self
    {
        return new self(
            id: $customer->id->toRfc4122(),
            email: $customer->email,
            firstName: $customer->firstName,
            lastName: $customer->lastName,
            gender: $customer->gender->value,
            country: $customer->country,
            bonusPercent: $customer->bonusPercent,
            realBalance: $customer->realBalance->toString(),
            bonusBalance: $customer->bonusBalance->toString(),
            createdAt: $customer->createdAt->format(DateTimeInterface::RFC3339_EXTENDED),
            updatedAt: $customer->updatedAt->format(DateTimeInterface::RFC3339_EXTENDED),
        );
    }
}

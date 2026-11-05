<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\EmailAlreadyTaken;
use App\Model\Customer;
use App\Repository\CustomerRepository;
use App\Request\CreateCustomerRequest;
use App\ValueObject\Gender;
use App\ValueObject\Money;
use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Uid\Uuid;

final readonly class CustomerService
{
    public const int MIN_BONUS_PERCENT = 5;
    public const int MAX_BONUS_PERCENT = 20;

    public function __construct(
        private CustomerRepository $customers
    ) {
    }

    /**
     * @throws EmailAlreadyTaken
     */
    public function create(CreateCustomerRequest $request): Customer
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));

        $customer = new Customer(
            id: Uuid::v7(),
            email: $request->email,
            firstName: $request->firstName,
            lastName: $request->lastName,
            gender: Gender::from($request->gender),
            country: $request->country,
            bonusPercent: random_int(self::MIN_BONUS_PERCENT, self::MAX_BONUS_PERCENT),
            realBalance: Money::zero(),
            bonusBalance: Money::zero(),
            createdAt: $now,
            updatedAt: $now,
        );

        $this->customers->add($customer);

        return $customer;
    }
}

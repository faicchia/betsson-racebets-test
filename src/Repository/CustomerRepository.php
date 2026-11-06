<?php

declare(strict_types=1);

namespace App\Repository;

use App\Exception\EmailAlreadyTaken;
use App\Model\Customer;
use App\ValueObject\Money;
use Symfony\Component\Uid\Uuid;

interface CustomerRepository
{
    /**
     * @throws EmailAlreadyTaken
     */
    public function add(Customer $customer): void;

    public function find(Uuid $id): ?Customer;

    /**
     * Updates only the given fields and sets updated_at to now.
     *
     * @param array<'email'|'firstName'|'lastName'|'gender'|'country', string> $fields keyed by Customer property
     *
     * @return bool false when customer is not found
     *
     * @throws EmailAlreadyTaken
     */
    public function updateDetails(Uuid $id, array $fields): bool;

    /**
     * Sets both balances and sets updated_at to now.
     *
     * @param array{realBalance: Money, bonusBalance: Money} $fields keyed by Customer property
     */
    public function updateBalances(Uuid $id, array $fields): void;

    /**
     * Locks the row until the surrounding DB transaction ends, so balance changes on one customer run one at a time.
     */
    public function findForUpdate(Uuid $id): ?Customer;
}

<?php

declare(strict_types=1);

namespace App\Repository;

use App\Exception\EmailAlreadyTaken;
use App\Model\Customer;
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
    public function update(Uuid $id, array $fields): bool;
}

<?php

declare(strict_types=1);

namespace App\Repository;

use App\Exception\EmailAlreadyTaken;
use App\Model\Customer;

interface CustomerRepository
{
    /**
     * @throws EmailAlreadyTaken
     */
    public function add(Customer $customer): void;
}

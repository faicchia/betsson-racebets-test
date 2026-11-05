<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;
use Symfony\Component\Uid\Uuid;

final class CustomerNotFound extends RuntimeException
{
    public static function withId(Uuid $id): self
    {
        return new self(sprintf("Customer '%s' not found.", $id->toRfc4122()));
    }
}

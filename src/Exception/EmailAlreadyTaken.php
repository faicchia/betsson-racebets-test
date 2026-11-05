<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class EmailAlreadyTaken extends RuntimeException
{
    public static function withEmail(string $email): self
    {
        return new self(sprintf("The email '%s' is already taken.", $email));
    }
}

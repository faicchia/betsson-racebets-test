<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class NothingToUpdate extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('The request must contain at least one field to update.');
    }
}

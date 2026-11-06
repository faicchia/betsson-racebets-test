<?php

declare(strict_types=1);

namespace App\Exception;

use RuntimeException;

final class InvalidReportRange extends RuntimeException
{
    public static function startsAfterEnd(): self
    {
        return new self('This value must not be after the end of the range.');
    }
}

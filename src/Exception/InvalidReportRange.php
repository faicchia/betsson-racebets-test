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

    public static function longerThan(int $months): self
    {
        return new self(sprintf('This value must be less than %d months before the end of the range.', $months));
    }
}

<?php

declare(strict_types=1);

namespace App\Model;

use App\ValueObject\Money;
use DateTimeImmutable;

final readonly class ReportRow
{
    public function __construct(
        public DateTimeImmutable $date,
        public string $country,
        public int $uniqueCustomers,
        public int $depositCount,
        public Money $depositTotal,
        public int $withdrawalCount,
        /** negative, or zero when there were no withdrawals */
        public Money $withdrawalTotal,
    ) {
    }
}

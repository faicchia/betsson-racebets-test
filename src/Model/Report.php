<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;

final readonly class Report
{
    public function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $to,
        /** @var list<ReportRow> */
        public array $data,
    ) {
    }
}

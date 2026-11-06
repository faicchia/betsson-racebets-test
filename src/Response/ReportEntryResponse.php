<?php

declare(strict_types=1);

namespace App\Response;

use App\Model\ReportRow;

final readonly class ReportEntryResponse
{
    private function __construct(
        public string $date,
        public string $country,
        public int $uniqueCustomers,
        public int $depositCount,
        public string $depositTotal,
        public int $withdrawalCount,
        public string $withdrawalTotal,
    ) {
    }

    public static function fromReportRow(ReportRow $row): self
    {
        return new self(
            date: $row->date->format('Y-m-d'),
            country: $row->country,
            uniqueCustomers: $row->uniqueCustomers,
            depositCount: $row->depositCount,
            depositTotal: $row->depositTotal->toString(),
            withdrawalCount: $row->withdrawalCount,
            withdrawalTotal: $row->withdrawalTotal->toString(),
        );
    }
}

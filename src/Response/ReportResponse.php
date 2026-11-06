<?php

declare(strict_types=1);

namespace App\Response;

use App\Model\Report;

final readonly class ReportResponse
{
    private function __construct(
        public string $from,
        public string $to,
        /** @var list<ReportEntryResponse> */
        public array $data,
    ) {
    }

    public static function fromReport(Report $report): self
    {
        return new self(
            from: $report->from->format('Y-m-d'),
            to: $report->to->format('Y-m-d'),
            data: array_map(ReportEntryResponse::fromReportRow(...), $report->data),
        );
    }
}

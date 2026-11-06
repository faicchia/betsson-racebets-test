<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\InvalidReportRange;
use App\Model\Report;
use App\Repository\TransactionRepository;
use DateTimeImmutable;
use DateTimeZone;

final readonly class ReportService
{
    public const int DEFAULT_DAYS = 7;

    public function __construct(
        private TransactionRepository $ledger,
    ) {
    }

    /**
     * Daily totals per country for an inclusive range of UTC dates.
     * The end defaults to today and the start to DEFAULT_DAYS days back from the end, both included.
     *
     * @throws InvalidReportRange when the start is after the end
     */
    public function dailyTotalsByCountry(?DateTimeImmutable $from, ?DateTimeImmutable $to): Report
    {
        $to ??= new DateTimeImmutable('today', new DateTimeZone('UTC'));
        $from ??= $to->modify(sprintf('-%d days', self::DEFAULT_DAYS - 1));

        if ($from > $to) {
            throw InvalidReportRange::startsAfterEnd();
        }

        return new Report($from, $to, $this->ledger->dailyTotalsByCountry($from, $to));
    }
}

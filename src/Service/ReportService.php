<?php

declare(strict_types=1);

namespace App\Service;

use App\Exception\InvalidReportRange;
use App\Model\Report;
use App\Repository\TransactionRepository;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;

final readonly class ReportService
{
    public const int DEFAULT_DAYS = 7;

    // bounds how much of the ledger a single request can aggregate
    public const int MAX_MONTHS = 3;

    public function __construct(
        private TransactionRepository $ledger,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Daily totals per country for an inclusive range of UTC dates.
     * The end defaults to today and the start to DEFAULT_DAYS days back from the end, both included.
     * The end must fall before the date MAX_MONTHS calendar months after the start.
     *
     * @throws InvalidReportRange when the start is after the end or the range is too long
     */
    public function dailyTotalsByCountry(?DateTimeImmutable $from, ?DateTimeImmutable $to): Report
    {
        $to ??= $this->clock->now()->setTime(0, 0);
        $from ??= $to->modify(sprintf('-%d days', self::DEFAULT_DAYS - 1));

        if ($from > $to) {
            throw InvalidReportRange::startsAfterEnd();
        }

        if ($to >= $from->modify(sprintf('+%d months', self::MAX_MONTHS))) {
            throw InvalidReportRange::longerThan(self::MAX_MONTHS);
        }

        return new Report($from, $to, $this->ledger->dailyTotalsByCountry($from, $to));
    }
}

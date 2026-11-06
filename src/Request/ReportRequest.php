<?php

declare(strict_types=1);

namespace App\Request;

use DateTimeImmutable;
use DateTimeZone;
use Symfony\Component\Validator\Constraints as Assert;

final readonly class ReportRequest
{
    public function __construct(
        #[Assert\Sequentially([new Assert\NotBlank(allowNull: true), new Assert\Date()])]
        public ?string $from = null,
        #[Assert\Sequentially([new Assert\NotBlank(allowNull: true), new Assert\Date()])]
        public ?string $to = null,
    ) {
    }

    public function fromDate(): ?DateTimeImmutable
    {
        return self::parse($this->from);
    }

    public function toDate(): ?DateTimeImmutable
    {
        return self::parse($this->to);
    }

    private static function parse(?string $date): ?DateTimeImmutable
    {
        if (null === $date) {
            return null;
        }

        // the leading ! resets the time to midnight; the validator has already checked the format
        return DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC')) ?: null;
    }
}

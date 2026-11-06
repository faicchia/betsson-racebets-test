<?php

declare(strict_types=1);

namespace App\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class WithdrawalRequest
{
    public const int MAX_AMOUNT = 10_000;

    public function __construct(
        #[Assert\Sequentially([
            new Assert\NotBlank(),
            new Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/', message: 'This value must be an amount with at most two decimals.'),
            new Assert\Positive(),
            new Assert\LessThanOrEqual(self::MAX_AMOUNT),
        ])]
        public string $amount = '',
    ) {
    }
}

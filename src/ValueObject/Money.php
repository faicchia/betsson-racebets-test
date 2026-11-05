<?php

declare(strict_types=1);

namespace App\ValueObject;

use BcMath\Number;

/**
 * A decimal amount with two decimals. Values go string → Money → string and never pass through a float.
 */
final readonly class Money
{
    private const int SCALE = 2;

    private function __construct(
        private Number $amount
    ) {
    }

    public static function zero(): self
    {
        return new self(new Number('0'));
    }

    /**
     * @param numeric-string $amount
     */
    public static function of(string $amount): self
    {
        return new self(new Number($amount));
    }

    public function toString(): string
    {
        // round() keeps the original scale, so bcadd() pads the result to exactly two decimals
        return bcadd($this->amount->round(self::SCALE)->value, '0', self::SCALE);
    }
}

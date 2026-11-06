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
        private Number $amount,
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

    public function add(self $other): self
    {
        return new self($this->amount + $other->amount);
    }

    public function subtract(self $other): self
    {
        return new self($this->amount - $other->amount);
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->amount > $other->amount;
    }

    /**
     * @return self the share rounded half-up to the cent, so the stored value is the one shown
     */
    public function percent(int $percent): self
    {
        return new self(($this->amount * $percent / 100)->round(self::SCALE));
    }

    public function toString(): string
    {
        // round() keeps the original scale, so bcadd() pads the result to exactly two decimals
        return bcadd($this->amount->round(self::SCALE)->value, '0', self::SCALE);
    }
}

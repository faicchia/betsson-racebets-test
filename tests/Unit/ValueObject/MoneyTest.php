<?php

declare(strict_types=1);

use App\ValueObject\Money;

it('adds two amounts', function () {
    expect(Money::of('100.50')->add(Money::of('0.75'))->toString())->toBe('101.25');
});

it('takes a whole percent of an amount', function (string $amount, int $percent, string $expected) {
    expect(Money::of($amount)->percent($percent)->toString())->toBe($expected);
})->with([
    'exact' => ['100.00', 10, '10.00'],
    'rounds half up to the cent' => ['0.05', 10, '0.01'],
    'rounds down below half a cent' => ['0.04', 10, '0.00'],
    'keeps cents' => ['33.33', 15, '5.00'],
    'large amount' => ['10000.00', 20, '2000.00'],
]);

it('rounds the percent itself, not only when it is formatted', function () {
    // 12.35 × 7% = 0.8645: two unrounded bonuses would add up to 1.729 and show as 1.73
    $bonus = Money::of('12.35')->percent(7);

    expect($bonus->add($bonus)->toString())->toBe('1.72');
});

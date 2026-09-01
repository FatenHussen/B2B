<?php

use Modules\Core\Domain\ValueObjects\Money;

it('parses a decimal string into minor units', function () {
    $money = Money::fromDecimal('2.50');

    expect($money->minor)->toBe(250)
        ->and($money->toDecimal())->toBe('2.50');
});

it('rejects a float constructor via fromDecimal type', function () {
    Money::fromDecimal('abc');
})->throws(InvalidArgumentException::class);

it('formats zero-padded fractions', function () {
    expect(Money::fromMinor(3)->toDecimal())->toBe('0.03');
});

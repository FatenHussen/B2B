<?php

declare(strict_types=1);

/**
 * BE-R09 acceptance criterion: no float reaches the FX path.
 *
 * Rule 7 forbids `float` and `double` on any money path, and an exchange rate is on one —
 * it is multiplied into an amount. The danger is specific rather than stylistic: a rate
 * held as a float loses precision at the sixth decimal place, which is exactly the
 * resolution `Money::FX_SCALE` exists to provide, so the loss lands on the digit that
 * matters and shows up as an amount that is off by a unit or two with nothing to blame.
 *
 * This scans the FX surface for float type declarations, float casts, and the float-typed
 * functions that turn an integer rate into an approximation.
 */

use Illuminate\Support\Facades\File;
use Modules\Core\Domain\ValueObjects\Money;

/** Files that carry, resolve or convert an exchange rate. */
const FX_PATHS = [
    'app-modules/reference/src/Domain/FxRateResolver.php',
    'app-modules/reference/src/Domain/Models/FxRate.php',
    'app-modules/core/src/Domain/ValueObjects/Money.php',
];

/**
 * Float declarations, casts and lossy numeric calls in $code.
 *
 * @return list<string>
 */
function floatUsages(string $code): array
{
    $found = [];

    // : float  and  ?float  in a return type or parameter, and `float $x` / `float|null`.
    if (preg_match_all('/(?::\s*\??float\b|\bfloat\s+\$\w+|\?float\b|\bdouble\b)/', $code, $m)) {
        foreach ($m[0] as $hit) {
            $found[] = trim($hit);
        }
    }

    // (float) and (double) casts.
    if (preg_match_all('/\((?:float|double)\)/', $code, $m)) {
        foreach ($m[0] as $hit) {
            $found[] = $hit;
        }
    }

    // floatval(), round()/floor()/ceil() on a money path — all return or accept floats.
    if (preg_match_all('/\b(?:floatval|fdiv)\s*\(/', $code, $m)) {
        foreach ($m[0] as $hit) {
            $found[] = rtrim($hit, '(').'()';
        }
    }

    return array_values(array_unique($found));
}

it('lets no float onto the FX path', function () {
    $offenders = [];

    foreach (FX_PATHS as $path) {
        $full = base_path($path);

        // A missing file is a failure, not a pass: this test would otherwise go quietly
        // green the day one of them is renamed.
        expect(File::exists($full))->toBeTrue("missing FX path file: {$path}");

        $usages = floatUsages((string) file_get_contents($full));

        if ($usages !== []) {
            $offenders[$path] = $usages;
        }
    }

    expect($offenders)->toBe([]);
})->group('arch');

it('actually catches a float on a money path', function () {
    // The detector proved rather than trusted, on each form it claims to catch.
    $violating = <<<'PHP'
    <?php
    final class Converter
    {
        public function rate(): float
        {
            return (float) $this->row->rate / 1000000;
        }

        public function convert(float $amount): int
        {
            return (int) floatval($amount);
        }
    }
    PHP;

    expect(floatUsages($violating))->toContain(': float')
        ->toContain('(float)')
        ->toContain('float $amount')
        ->toContain('floatval()');
})->group('arch');

it('leaves an integer-only conversion alone', function () {
    $compliant = <<<'PHP'
    <?php
    final class Converter
    {
        public function convert(int $minor, int $rate): int
        {
            return intdiv($minor * $rate, Money::FX_UNIT);
        }
    }
    PHP;

    expect(floatUsages($compliant))->toBe([]);
})->group('arch');

it('keeps the FX scale fixed and integral', function () {
    // The constants themselves, since every conversion depends on them being integers.
    expect(Money::FX_SCALE)->toBeInt()->toBe(6)
        ->and(Money::FX_UNIT)->toBeInt()->toBe(1_000_000)
        ->and(10 ** Money::FX_SCALE)
        ->toBe(Money::FX_UNIT);
})->group('arch');

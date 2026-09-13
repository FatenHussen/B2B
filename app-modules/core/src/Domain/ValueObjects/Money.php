<?php

namespace Modules\Core\Domain\ValueObjects;

use InvalidArgumentException;

/**
 * An amount in the currency's minor unit (BR-AD-18 / ADR-04).
 *
 * Never constructed from a float. Decimal strings are parsed as text.
 */
final class Money
{
    /**
     * The scale of every exchange rate, everywhere. A rate of 1.0 is `1_000_000`.
     *
     * Fixed here rather than stored per row, and that is the whole point. A `scale`
     * column would let two rows on the same currency pair carry different scales, and
     * the first conversion between them would be wrong by a factor of ten with nothing
     * in the data to reveal it — no exception, no mismatch, just a number that is off.
     * One constant means a rate read from any row means the same thing.
     *
     * Six decimal places is the resolution: enough for a thin-margin pair without
     * exceeding what a bigInteger holds once multiplied into an amount.
     *
     * This is not the money scale. `Money::$scale` is how many minor units a *currency*
     * has — 0 for SYP, 2 for USD — and it comes from `currencies.decimals`. The two are
     * unrelated and must not be substituted for each other.
     */
    public const FX_SCALE = 6;

    /**
     * 10 ** FX_SCALE, precomputed. The denominator of every rate.
     */
    public const FX_UNIT = 1_000_000;

    private function __construct(
        public readonly int $minor,
        public readonly string $currency,
        public readonly int $scale,
    ) {}

    public static function fromMinor(int $minor, ?string $currency = null, ?int $scale = null): self
    {
        return new self(
            $minor,
            $currency ?? (string) config('core.default_currency', 'SYP'),
            $scale ?? (int) config('core.money_scale', 2),
        );
    }

    public static function zero(?string $currency = null): self
    {
        return self::fromMinor(0, $currency);
    }

    /**
     * Parse a decimal string such as "2.50" or "2". Floats are rejected.
     */
    public static function fromDecimal(string|int $amount, ?string $currency = null, ?int $scale = null): self
    {
        $scale ??= (int) config('core.money_scale', 2);
        $currency ??= (string) config('core.default_currency', 'SYP');

        if (is_int($amount)) {
            return new self($amount * (10 ** $scale), $currency, $scale);
        }

        if (! preg_match('/^-?\d+(\.\d+)?$/', $amount)) {
            throw new InvalidArgumentException("Invalid money decimal: {$amount}");
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $fraction = str_pad(substr($fraction, 0, $scale), $scale, '0');
        $sign = str_starts_with($whole, '-') ? -1 : 1;
        $whole = ltrim($whole, '+-');

        return new self($sign * ((int) $whole * (10 ** $scale) + (int) $fraction), $currency, $scale);
    }

    public function toDecimal(): string
    {
        $abs = abs($this->minor);
        $whole = intdiv($abs, 10 ** $this->scale);
        $fraction = str_pad((string) ($abs % (10 ** $this->scale)), $this->scale, '0', STR_PAD_LEFT);
        $sign = $this->minor < 0 ? '-' : '';

        return $this->scale === 0
            ? $sign.(string) $whole
            : "{$sign}{$whole}.{$fraction}";
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor
            && $this->currency === $other->currency
            && $this->scale === $other->scale;
    }

    public function __toString(): string
    {
        return $this->toDecimal();
    }
}

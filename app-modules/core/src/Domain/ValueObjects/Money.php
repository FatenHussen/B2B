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

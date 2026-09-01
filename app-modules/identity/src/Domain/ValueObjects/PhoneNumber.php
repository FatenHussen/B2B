<?php

namespace Modules\Identity\Domain\ValueObjects;

use InvalidArgumentException;

final class PhoneNumber
{
    private function __construct(public readonly string $value) {}

    public static function make(string $input): self
    {
        $normalized = self::normalize($input);

        if (! self::isValid($normalized)) {
            throw new InvalidArgumentException("Invalid Syrian phone number: {$input}");
        }

        return new self($normalized);
    }

    public static function normalize(string $input): string
    {
        $digits = preg_replace('/[^\d+]/', '', $input) ?? '';
        $digits = ltrim($digits, '+');

        if (str_starts_with($digits, '00963')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '0')) {
            $digits = '963'.substr($digits, 1);
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '9')) {
            $digits = '963'.$digits;
        }

        return '+'.$digits;
    }

    public static function isValid(string $normalized): bool
    {
        return (bool) preg_match('/^\+9639\d{8}$/', $normalized);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

<?php

namespace Modules\Core\Domain\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Domain\ValueObjects\Money;

/**
 * Stores BIGINT minor units; exposes a decimal string on the model so
 * resources stay JSON-safe (never a float).
 *
 * @implements CastsAttributes<string|null, mixed>
 */
final class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return Money::fromMinor((int) $value)->toDecimal();
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Money) {
            return $value->minor;
        }

        return Money::fromDecimal((string) $value)->minor;
    }
}

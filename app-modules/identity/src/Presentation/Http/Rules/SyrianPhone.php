<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Modules\Identity\Domain\ValueObjects\PhoneNumber;

final class SyrianPhone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! PhoneNumber::isValid(PhoneNumber::normalize($value))) {
            $fail('validation.syrian_phone')->translate();
        }
    }
}

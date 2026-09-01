<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Concerns;

use Modules\Identity\Domain\ValueObjects\PhoneNumber;

trait NormalizesSyrianPhone
{
    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        if (is_string($phone) && $phone !== '') {
            $this->merge(['phone' => PhoneNumber::normalize($phone)]);
        }
    }
}

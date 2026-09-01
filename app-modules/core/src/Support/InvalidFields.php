<?php

declare(strict_types=1);

namespace Modules\Core\Support;

use Illuminate\Validation\ValidationException;

final class InvalidFields
{
    /**
     * Field-level 422 that the API envelope renderer already maps to `validation_failed`.
     *
     * @param  array<string, string>  $fields  field => translation key
     */
    public static function throw(array $fields): never
    {
        $messages = [];

        foreach ($fields as $field => $key) {
            $messages[$field] = [__($key)];
        }

        throw ValidationException::withMessages($messages);
    }
}

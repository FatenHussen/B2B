<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

/**
 * Reps authenticate with a Syrian mobile. Email is prohibited (not optional).
 */
final class RegisterRepRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'supply_channel_id' => ['required', 'integer'],
            'activity_type_id' => ['required', 'integer'],
            'zone_ids' => ['required', 'array', 'min:1'],
            'zone_ids.*' => ['integer'],
            'note' => ['nullable', 'string', 'max:500'],
            'email' => ['prohibited'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Loyalty\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class StoreLoyaltyRewardRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'points_cost' => ['required', 'integer', 'min:1'],
            'stock' => ['required', 'integer', 'min:0'],
            'expires_at' => ['nullable', 'date'],
        ];
    }
}

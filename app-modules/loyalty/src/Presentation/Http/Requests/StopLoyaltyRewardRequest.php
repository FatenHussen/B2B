<?php

declare(strict_types=1);

namespace Modules\Loyalty\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class StopLoyaltyRewardRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}

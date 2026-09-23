<?php

declare(strict_types=1);

namespace Modules\Loyalty\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class RedeemLoyaltyRewardRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reward_id' => ['required', 'integer', 'min:1'],
        ];
    }
}

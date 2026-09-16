<?php

declare(strict_types=1);

namespace Modules\Loyalty\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class UpdateLoyaltyRulesRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'retailer_rules' => ['required', 'array'],
            'rep_rules' => ['required', 'array'],
            'tiers' => ['required', 'array'],
        ];
    }
}

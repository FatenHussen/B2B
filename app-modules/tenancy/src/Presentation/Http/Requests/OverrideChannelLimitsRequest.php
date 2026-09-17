<?php

declare(strict_types=1);

namespace Modules\Tenancy\Presentation\Http\Requests;

use Modules\Core\Contracts\ChannelLimits;
use Modules\Core\Http\ApiFormRequest;

/**
 * EP-AD-055 — BE-T12. Every limit is an integer; `reason` is mandatory; `temporary_until`
 * is optional and, when present, in the future — an override that expired on arrival
 * would revert before anyone saw it.
 */
final class OverrideChannelLimitsRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'limits' => ['required', 'array', 'min:1'],
            'temporary_until' => ['nullable', 'date', 'after:now'],
            'reason' => ['required', 'string', 'max:500'],
        ];

        foreach (ChannelLimits::KEYS as $key) {
            $rules['limits.'.$key] = ['sometimes', 'integer', 'min:0'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['reason.required' => __('tenancy.limit_override_reason_required')];
    }
}

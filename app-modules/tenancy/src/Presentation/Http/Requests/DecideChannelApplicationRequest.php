<?php

declare(strict_types=1);

namespace Modules\Tenancy\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DecideChannelApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
            'plan_id' => ['required_if:decision,approve', 'integer', Rule::exists('channel_plans', 'id')],
            'trial_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
        ];
    }
}

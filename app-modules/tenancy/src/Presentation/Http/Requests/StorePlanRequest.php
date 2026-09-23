<?php

declare(strict_types=1);

namespace Modules\Tenancy\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Tenancy\Domain\Enums\PlanOnExceed;

final class StorePlanRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:96'],
            'key' => ['required', 'string', 'max:32', 'alpha_dash', Rule::unique('channel_plans', 'key')],
            'price_monthly' => ['required', 'integer', 'min:0'],
            'price_yearly' => ['required', 'integer', 'min:0'],
            'currency_id' => ['required', 'integer', Rule::exists('currencies', 'id')],
            'limits' => ['required', 'array'],
            'limits.users' => ['required', 'integer', 'min:0'],
            'limits.warehouses' => ['required', 'integer', 'min:0'],
            'limits.reps' => ['required', 'integer', 'min:0'],
            'limits.skus' => ['required', 'integer', 'min:0'],
            'limits.storage_mb' => ['required', 'integer', 'min:0'],
            'limits.otp_monthly' => ['required', 'integer', 'min:0'],
            'features' => ['sometimes', 'array'],
            'features.*' => ['string', 'max:64'],
            'on_exceed' => ['required', Rule::enum(PlanOnExceed::class)],
            'trial_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'is_public' => ['sometimes', 'boolean'],
        ];
    }
}

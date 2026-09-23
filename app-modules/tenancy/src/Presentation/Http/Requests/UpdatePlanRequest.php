<?php

declare(strict_types=1);

namespace Modules\Tenancy\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Tenancy\Domain\Enums\PlanOnExceed;
use Modules\Tenancy\Domain\Enums\PlanStatus;

final class UpdatePlanRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:96'],
            'price_monthly' => ['sometimes', 'integer', 'min:0'],
            'price_yearly' => ['sometimes', 'integer', 'min:0'],
            'currency_id' => ['sometimes', 'integer', Rule::exists('currencies', 'id')],
            'limits' => ['sometimes', 'array'],
            'limits.users' => ['required_with:limits', 'integer', 'min:0'],
            'limits.warehouses' => ['required_with:limits', 'integer', 'min:0'],
            'limits.reps' => ['required_with:limits', 'integer', 'min:0'],
            'limits.skus' => ['required_with:limits', 'integer', 'min:0'],
            'limits.storage_mb' => ['required_with:limits', 'integer', 'min:0'],
            'limits.otp_monthly' => ['required_with:limits', 'integer', 'min:0'],
            'features' => ['sometimes', 'array'],
            'features.*' => ['string', 'max:64'],
            'on_exceed' => ['sometimes', Rule::enum(PlanOnExceed::class)],
            'trial_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'is_public' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::enum(PlanStatus::class)],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }
}

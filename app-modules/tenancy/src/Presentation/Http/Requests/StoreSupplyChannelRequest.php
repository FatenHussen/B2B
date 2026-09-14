<?php

declare(strict_types=1);

namespace Modules\Tenancy\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;

/**
 * EP-AD-051 body. The catalog is the contract; fields here match it, not the older
 * name + slug shape the prior test documented.
 *
 * `legal_form` is free text (max 64): the catalog shows `llc` and names no other value.
 * An enum guessed here would mean a migration the day the real list appears — the
 * catalog must name the values before this rule tightens.
 *
 * `custom_discount` is a whole-number percentage 0–100. It is not on a money path today;
 * BE4-BIL01 reads it. The field is `custom_discount`, not `discount_amount`.
 *
 * `status` is prohibited (rule 8). A new channel starts in `provisioning` from the
 * column default and leaves it only through ChannelLifecycle (BE-T05).
 *
 * `zone_ids` are validated and stored on the provision-job payload; `channel_zone` rows
 * are written by the job in BE-T05.
 */
final class StoreSupplyChannelRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'slug' => ['required', 'string', 'max:191', 'alpha_dash', 'unique:supply_channels,slug'],
            // Catalog must name the allowed values before this becomes an enum.
            'legal_form' => ['required', 'string', 'max:64'],
            'cr_number' => ['required', 'string', 'max:64'],
            'documents' => ['nullable', 'array'],
            'documents.*' => ['integer', 'min:1'],
            'governorate_ids' => ['required', 'array', 'min:1'],
            'governorate_ids.*' => ['integer', 'distinct', 'exists:governorates,id'],
            'zone_ids' => ['required', 'array', 'min:1'],
            'zone_ids.*' => ['integer', 'distinct', 'exists:zones,id'],
            'activity_type_ids' => ['required', 'array', 'min:1'],
            'activity_type_ids.*' => ['integer', 'distinct', 'exists:activity_types,id'],
            'logo' => ['nullable', 'integer', 'min:1'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
            'plan_id' => ['required', 'integer', Rule::exists('channel_plans', 'id')->where('is_active', true)],
            'billing_cycle' => ['required', 'string', Rule::in(['monthly', 'yearly'])],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'limits' => ['required', 'array'],
            'limits.users' => ['required', 'integer', 'min:0'],
            'limits.warehouses' => ['required', 'integer', 'min:0'],
            'limits.reps' => ['required', 'integer', 'min:0'],
            'limits.skus' => ['required', 'integer', 'min:0'],
            'limits.storage_mb' => ['required', 'integer', 'min:0'],
            'custom_discount' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'manager' => ['required', 'array'],
            'manager.name' => ['required', 'string', 'max:191'],
            'manager.phone' => ['required', 'string', 'max:32'],
            'manager.email' => ['nullable', 'email', 'max:191'],
            'manager.invite_via' => ['required', 'string', 'max:32'],
            'status' => ['prohibited'],
        ];
    }
}

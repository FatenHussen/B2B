<?php

declare(strict_types=1);

namespace Modules\Tenancy\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

/**
 * EP-AD-062 body. `reason` is required (BR-AD-11). `status` is prohibited (rule 8).
 *
 * `legal_form` stays free text (max 64): the catalog shows `llc` and names no other value.
 */
final class UpdateSupplyChannelRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:191'],
            'legal_form' => ['sometimes', 'nullable', 'string', 'max:64'],
            'cr_number' => ['sometimes', 'nullable', 'string', 'max:64'],
            'activity_type_ids' => ['sometimes', 'array'],
            'activity_type_ids.*' => ['integer', 'distinct', 'exists:activity_types,id'],
            'internal_note' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'reason' => ['required', 'string', 'max:255'],
            'status' => ['prohibited'],
        ];
    }
}

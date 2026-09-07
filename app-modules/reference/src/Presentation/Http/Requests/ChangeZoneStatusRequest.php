<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;
use Modules\Reference\Domain\Enums\ZoneStatus;

final class ChangeZoneStatusRequest extends ApiFormRequest
{
    /**
     * `contractValues()`, not `values()`: EP-AD-034 sends `disabled`, while the column
     * stores `inactive`. Validating against the contract's vocabulary means a request
     * carrying the stored spelling is refused 422 like any other unknown word, instead of
     * slipping through and writing a status the contract never named.
     *
     * `reason` is required — EP-AD-042A: every ref change carries a reason and is audited
     * before and after.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(ZoneStatus::contractValues())],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}

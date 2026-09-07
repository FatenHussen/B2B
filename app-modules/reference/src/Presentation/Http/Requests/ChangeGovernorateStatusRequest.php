<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;
use Modules\Reference\Domain\Enums\RefStatus;

final class ChangeGovernorateStatusRequest extends ApiFormRequest
{
    /**
     * `reason` is required, not optional. EP-AD-042A carries
     * "Every ref change requires a reason and is audited before/after", and BE-R02's
     * acceptance criteria name it directly: an update without a reason is refused 422.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(RefStatus::class)],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}

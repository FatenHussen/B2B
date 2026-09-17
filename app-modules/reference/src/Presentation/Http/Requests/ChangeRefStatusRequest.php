<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;
use Modules\Reference\Domain\Enums\RefStatus;

/**
 * EP-AD-043B/C/D/E — the shared status body for activity types, root categories, sale
 * units and equipment. Governorates, zones and currencies keep their own request classes
 * because their vocabularies or permissions differ (BE-R03, BE-R08).
 *
 * `reason` is required on every reference mutation (BE-R12).
 */
final class ChangeRefStatusRequest extends ApiFormRequest
{
    /**
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

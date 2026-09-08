<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;
use Modules\Reference\Domain\Enums\RefStatus;

final class ChangeCurrencyStatusRequest extends ApiFormRequest
{
    /**
     * EP-AD-043F: {status, reason}. Same shape as governorates — rule 12, a reference
     * entity is disabled and never deleted, and the change carries a reason.
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

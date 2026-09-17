<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

/** EP-AD-042E. `reason` is required — BE-R12. */
final class UpdateSaleUnitRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:191'],
            'abbr' => ['sometimes', 'nullable', 'string', 'max:16'],
            'default_factor' => ['sometimes', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}

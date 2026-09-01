<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;
use Modules\Reference\Domain\Enums\ZoneStatus;

final class StoreZoneRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'governorate_id' => ['required', 'integer', 'exists:governorates,id'],
            'name' => ['required', 'string', 'max:191'],
            'polygon' => ['nullable', 'array'],
            'status' => ['nullable', Rule::in(ZoneStatus::values())],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class StoreGovernorateRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name_ar' => ['required', 'string', 'max:191'],
            'name_en' => ['required', 'string', 'max:191'],
            'code' => ['required', 'string', 'max:16', 'unique:governorates,code'],
        ];
    }
}

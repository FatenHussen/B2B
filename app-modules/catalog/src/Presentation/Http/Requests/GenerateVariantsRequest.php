<?php

declare(strict_types=1);

namespace Modules\Catalog\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class GenerateVariantsRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'axes' => ['required', 'array', 'min:1'],
            'axes.*.name' => ['required', 'string', 'max:80'],
            'axes.*.values' => ['required', 'array', 'min:1'],
            'axes.*.values.*' => ['required', 'string', 'max:80'],
        ];
    }
}

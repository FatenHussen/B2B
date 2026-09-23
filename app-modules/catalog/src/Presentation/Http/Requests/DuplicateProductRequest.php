<?php

declare(strict_types=1);

namespace Modules\Catalog\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class DuplicateProductRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:80'],
            'name_ar' => ['nullable', 'string', 'max:200'],
        ];
    }
}

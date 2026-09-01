<?php

declare(strict_types=1);

namespace Modules\Catalog\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class ReorderCategoriesRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'moves' => ['required', 'array', 'min:1'],
            'moves.*.id' => ['required', 'integer', 'min:1'],
            'moves.*.parent_id' => ['nullable', 'integer'],
            'moves.*.order' => ['required', 'integer', 'min:0'],
        ];
    }
}

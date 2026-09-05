<?php

declare(strict_types=1);

namespace Modules\Ordering\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class AddRepCartLineRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'retailer_id' => ['required', 'integer', 'min:1'],
            'product_id' => ['required', 'integer', 'min:1'],
            'variant_id' => ['nullable', 'integer'],
            'qty' => ['required', 'integer', 'min:1'],
        ];
    }
}

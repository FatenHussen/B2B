<?php

declare(strict_types=1);

namespace Modules\Ordering\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Core\Http\ApiFormRequest;
use Modules\Ordering\Domain\Enums\CartLineSource;

final class AddCartLineRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'min:1'],
            'variant_id' => ['nullable', 'integer', 'min:1'],
            'qty' => ['required', 'integer', 'min:1'],
            'source' => ['nullable', Rule::enum(CartLineSource::class)],
        ];
    }
}

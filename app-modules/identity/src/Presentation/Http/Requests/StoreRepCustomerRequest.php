<?php

declare(strict_types=1);

namespace Modules\Identity\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;
use Modules\Identity\Presentation\Http\Concerns\NormalizesSyrianPhone;
use Modules\Identity\Presentation\Http\Rules\SyrianPhone;

final class StoreRepCustomerRequest extends ApiFormRequest
{
    use NormalizesSyrianPhone;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'shop_name' => ['required', 'string', 'max:160'],
            'owner_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'string', new SyrianPhone],
            'zone_id' => ['required', 'integer', 'min:1'],
            'activity_type_id' => ['required', 'integer', 'min:1'],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'client_op_id' => ['required', 'string', 'max:80'],
        ];
    }
}

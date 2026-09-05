<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class ScanPickRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'barcode' => ['required', 'string'],
            'qty' => ['required', 'integer', 'min:1'],
        ];
    }
}

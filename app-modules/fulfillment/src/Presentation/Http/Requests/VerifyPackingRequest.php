<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class VerifyPackingRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'scans' => ['required', 'array'],
            'scans.*.barcode' => ['required', 'string'],
            'scans.*.qty' => ['required', 'integer', 'min:0'],
        ];
    }
}

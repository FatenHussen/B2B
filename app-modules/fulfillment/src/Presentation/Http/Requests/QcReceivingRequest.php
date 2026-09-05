<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class QcReceivingRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array'],
            'lines.*.line_id' => ['required', 'integer'],
            'lines.*.decision' => ['required', 'string'],
            'lines.*.qty' => ['required', 'integer', 'min:0'],
            'lines.*.photos' => ['nullable', 'array'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class ManualPickRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['qty' => ['required', 'integer', 'min:0']];
    }
}

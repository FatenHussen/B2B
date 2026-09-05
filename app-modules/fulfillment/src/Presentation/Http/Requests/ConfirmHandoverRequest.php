<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class ConfirmHandoverRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['temp_code' => ['required', 'string', 'size:4']];
    }
}

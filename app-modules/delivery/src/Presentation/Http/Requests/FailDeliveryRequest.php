<?php

declare(strict_types=1);

namespace Modules\Delivery\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class FailDeliveryRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['reason' => ['required', 'string']];
    }
}

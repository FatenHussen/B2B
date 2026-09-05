<?php

declare(strict_types=1);

namespace Modules\Delivery\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class PostponeDeliveryRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date'],
            'reason' => ['required', 'string'],
        ];
    }
}

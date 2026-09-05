<?php

declare(strict_types=1);

namespace Modules\Ordering\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class ScheduleSubOrderRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'scheduled_at' => ['required', 'date'],
            'reason' => ['nullable', 'string'],
        ];
    }
}

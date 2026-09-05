<?php

declare(strict_types=1);

namespace Modules\Delivery\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class PingRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'pings' => ['required', 'array', 'min:1'],
            'pings.*.lat' => ['required', 'numeric'],
            'pings.*.lng' => ['required', 'numeric'],
            'pings.*.at' => ['required', 'date'],
            'pings.*.accuracy' => ['nullable', 'integer'],
        ];
    }
}

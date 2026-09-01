<?php

declare(strict_types=1);

namespace Modules\Access\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class ExportAuditRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'filters' => ['nullable', 'array'],
            'filters.date_from' => ['nullable', 'date'],
            'filters.date_to' => ['nullable', 'date'],
            'filters.actor' => ['nullable'],
            'filters.action' => ['nullable', 'string'],
            'filters.channel_id' => ['nullable', 'integer'],
            'format' => ['nullable', 'in:xlsx,csv'],
        ];
    }
}

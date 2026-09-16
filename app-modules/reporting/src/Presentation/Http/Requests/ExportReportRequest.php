<?php

declare(strict_types=1);

namespace Modules\Reporting\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class ExportReportRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'format' => ['required', 'string', 'in:xlsx,pdf,csv'],
            'filters' => ['nullable', 'array'],
        ];
    }
}

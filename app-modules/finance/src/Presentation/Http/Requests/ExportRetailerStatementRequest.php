<?php

declare(strict_types=1);

namespace Modules\Finance\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class ExportRetailerStatementRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date', 'after_or_equal:date_from'],
            'channel_id' => ['nullable', 'integer', 'min:1'],
            'format' => ['required', 'string', 'in:pdf,xlsx,csv'],
        ];
    }
}

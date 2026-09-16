<?php

declare(strict_types=1);

namespace Modules\Finance\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class IssueCreditNoteRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.line_id' => ['required', 'integer', 'min:1'],
            'lines.*.qty' => ['required', 'integer', 'min:1'],
            'lines.*.amount' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:255'],
            'approval_request_id' => ['nullable', 'integer', 'min:1'],
            'approval_reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}

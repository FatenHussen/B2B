<?php

declare(strict_types=1);

namespace Modules\Finance\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class RecordRepWithdrawalRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1'],
            'operation_no' => ['required', 'string', 'max:32'],
            'operated_at' => ['required', 'date'],
        ];
    }
}

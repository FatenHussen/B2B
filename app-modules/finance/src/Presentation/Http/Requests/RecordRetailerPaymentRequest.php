<?php

declare(strict_types=1);

namespace Modules\Finance\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class RecordRetailerPaymentRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'receipt_no' => ['required', 'string', 'max:32'],
            'rep_id' => ['required', 'integer', 'min:1'],
            'supply_channel_id' => ['required', 'integer', 'min:1'],
            'invoice_no' => ['nullable', 'string', 'max:32'],
            'amount' => ['required', 'integer', 'min:1'],
        ];
    }
}

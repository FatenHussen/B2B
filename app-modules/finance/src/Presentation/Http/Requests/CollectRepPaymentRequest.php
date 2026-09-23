<?php

declare(strict_types=1);

namespace Modules\Finance\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class CollectRepPaymentRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'receipt_no' => ['required', 'string', 'max:32'],
            'retailer_id' => ['required', 'integer', 'min:1'],
            'invoice_no' => ['nullable', 'string', 'max:32'],
            'amount' => ['required', 'integer', 'min:1'],
            'paid_at' => ['required', 'date'],
            'client_op_id' => ['required', 'string', 'max:80'],
        ];
    }
}

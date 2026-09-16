<?php

declare(strict_types=1);

namespace Modules\Finance\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class RecordOfficePaymentRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'retailer_id' => ['required', 'integer', 'min:1'],
            'amount' => ['required', 'integer', 'min:1'],
            'method' => ['required', 'string', 'in:cash,bank,card'],
            'invoice_id' => ['nullable', 'integer', 'min:1'],
        ];
    }
}

<?php

declare(strict_types=1);

namespace Modules\Finance\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class UpsertRetailerCreditRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'credit_limit' => ['required', 'integer', 'min:0'],
            'grace_days' => ['required', 'integer', 'min:0'],
            'on_exceed' => ['required', 'string', 'in:warn,block,manual_approval'],
        ];
    }
}

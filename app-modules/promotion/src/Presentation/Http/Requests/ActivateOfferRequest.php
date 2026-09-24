<?php

declare(strict_types=1);

namespace Modules\Promotion\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class ActivateOfferRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:255'],
        ];
    }
}

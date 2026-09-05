<?php

declare(strict_types=1);

namespace Modules\Fulfillment\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class ApproveStocktakeRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['reason' => ['required', 'string']];
    }
}

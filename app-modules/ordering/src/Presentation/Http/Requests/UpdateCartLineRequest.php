<?php

declare(strict_types=1);

namespace Modules\Ordering\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class UpdateCartLineRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return ['qty' => ['required', 'integer', 'min:0']];
    }
}

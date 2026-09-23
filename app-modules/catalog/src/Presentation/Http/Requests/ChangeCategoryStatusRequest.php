<?php

declare(strict_types=1);

namespace Modules\Catalog\Presentation\Http\Requests;

use Illuminate\Validation\Rule;
use Modules\Catalog\Domain\Enums\CategoryStatus;
use Modules\Core\Http\ApiFormRequest;

final class ChangeCategoryStatusRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(CategoryStatus::class)],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}

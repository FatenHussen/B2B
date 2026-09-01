<?php

declare(strict_types=1);

namespace Modules\Catalog\Presentation\Http\Requests;

use Modules\Core\Http\ApiFormRequest;

final class ImportCatalogRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'file' => ['required', 'file'],
            'type' => ['required', 'in:products'],
            'dry_run' => ['nullable', 'boolean'],
        ];
    }
}

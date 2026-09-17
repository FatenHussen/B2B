<?php

declare(strict_types=1);

namespace Modules\Reference\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Core\Http\ApiController;
use Modules\Reference\Application\Actions\ImportReferences;
use Modules\Reference\Domain\Enums\ReferenceEntity;
use Modules\Reference\Presentation\Http\Requests\ImportReferencesRequest;

/**
 * BE-R11 — EP-AD-041 `POST /platform/refs/import` (multipart).
 *
 * `dry_run=true` previews and writes nothing. `dry_run=false` executes, and because the
 * idempotency middleware hashes method, path and body, a second execution under the
 * same key replays the first response instead of importing twice — the ticket's third
 * criterion, proven end to end in the feature test.
 */
final class ReferenceImportController extends ApiController
{
    public function store(ImportReferencesRequest $request, ImportReferences $import): JsonResponse
    {
        $file = $request->file('file');
        $csv = $file !== null ? (string) file_get_contents($file->getRealPath()) : '';

        $result = $import(
            ReferenceEntity::from((string) $request->validated('type')),
            $csv,
            (bool) $request->validated('dry_run'),
            $request->user(),
            $file?->getClientOriginalName(),
        );

        return $this->ok($result);
    }
}

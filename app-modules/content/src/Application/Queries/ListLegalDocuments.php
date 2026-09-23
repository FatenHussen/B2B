<?php

declare(strict_types=1);

namespace Modules\Content\Application\Queries;

use Illuminate\Http\Request;
use Modules\Content\Domain\Models\LegalDocument;

final class ListLegalDocuments
{
    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(Request $request): array
    {
        $query = LegalDocument::query()->orderByDesc('effective_from');

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        return $query->get()->map(fn (LegalDocument $doc) => [
            'id' => $doc->id,
            'type' => $doc->type,
            'version' => $doc->version,
            'effective_from' => $doc->effective_from?->toDateString(),
            'approved_by' => $doc->approved_by,
            'requires_reconsent' => $doc->requires_reconsent,
        ])->all();
    }
}

<?php

declare(strict_types=1);

namespace Modules\Content\Application\Queries;

use Illuminate\Http\Request;
use Modules\Content\Domain\Models\HelpGuide;

final class ListHelpGuides
{
    /**
     * @return list<array<string, mixed>>
     */
    public function __invoke(Request $request): array
    {
        $query = HelpGuide::query()->orderBy('order')->orderBy('id');

        $audience = $request->input('filter.audience') ?? $request->input('filter[audience]');
        if (is_string($audience) && $audience !== '') {
            $query->where('audience', $audience);
        }

        return $query->get()->map(fn (HelpGuide $guide) => [
            'id' => $guide->id,
            'audience' => $guide->audience,
            'title' => $guide->title,
            'status' => $guide->status,
        ])->all();
    }
}

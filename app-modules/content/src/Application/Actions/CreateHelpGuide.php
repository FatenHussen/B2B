<?php

declare(strict_types=1);

namespace Modules\Content\Application\Actions;

use Modules\Content\Domain\Models\HelpGuide;

final class CreateHelpGuide
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{id: int}
     */
    public function __invoke(array $data): array
    {
        $guide = HelpGuide::query()->create([
            'audience' => (string) $data['audience'],
            'title' => (string) $data['title'],
            'body' => (string) $data['body'],
            'status' => (string) ($data['status'] ?? 'draft'),
            'order' => (int) ($data['order'] ?? 0),
        ]);

        return ['id' => (int) $guide->id];
    }
}

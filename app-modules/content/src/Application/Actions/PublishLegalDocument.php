<?php

declare(strict_types=1);

namespace Modules\Content\Application\Actions;

use Modules\Content\Domain\Models\LegalDocument;
use Modules\Core\Contracts\RecordsAudit;

final class PublishLegalDocument
{
    public function __construct(private readonly RecordsAudit $audit) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{id: int, version: string}
     */
    public function __invoke(array $data, object $actor): array
    {
        $type = (string) $data['type'];
        $effectiveFrom = (string) $data['effective_from'];
        $version = substr($effectiveFrom, 0, 7); // YYYY-MM

        $doc = LegalDocument::query()->create([
            'type' => $type,
            'version' => $version,
            'body_ar' => (string) $data['body_ar'],
            'effective_from' => $effectiveFrom,
            'requires_reconsent' => (bool) ($data['requires_reconsent'] ?? false),
            'approved_by' => method_exists($actor, 'getAuthIdentifier') ? (int) $actor->getAuthIdentifier() : null,
        ]);

        $this->audit->record(
            action: 'content.legal.published',
            actor: $actor,
            subjectType: LegalDocument::class,
            subjectId: (int) $doc->id,
            properties: [
                'type' => $type,
                'version' => $version,
                'reason' => (string) ($data['reason'] ?? ''),
            ],
        );

        return ['id' => (int) $doc->id, 'version' => $version];
    }
}

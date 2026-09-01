<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure\Audit;

use Illuminate\Http\Request;
use Modules\Core\Contracts\RecordsAudit;
use Modules\Core\Domain\Models\AuditLog;

final class EloquentRecordsAudit implements RecordsAudit
{
    public function record(
        string $action,
        ?object $actor = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $properties = [],
        ?int $channelId = null,
        ?string $ip = null,
    ): void {
        $actorId = null;
        $actorType = null;

        if ($actor !== null && method_exists($actor, 'getAuthIdentifier')) {
            $actorId = (int) $actor->getAuthIdentifier();
            $actorType = $actor::class;
        }

        if ($channelId !== null) {
            $properties['channel_id'] = $channelId;
        }

        AuditLog::query()->create([
            'actor_id' => $actorId,
            'actor_type' => $actorType,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'channel_id' => $channelId,
            'properties' => $properties === [] ? null : $properties,
            'ip' => $ip ?? request()?->ip(),
            'created_at' => now(),
        ]);
    }
}

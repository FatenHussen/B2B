<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface RecordsAudit
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public function record(
        string $action,
        ?object $actor = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $properties = [],
        ?int $channelId = null,
        ?string $ip = null,
    ): void;
}

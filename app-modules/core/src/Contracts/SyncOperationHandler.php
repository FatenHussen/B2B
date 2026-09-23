<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * One offline-push verb. Handlers live in the module that owns the write
 * (Ordering, Catalog, Identity). Sync tags them and never imports those models.
 */
interface SyncOperationHandler
{
    public function handles(string $type): bool;

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: string, server_id: int|null, error: string|null}
     */
    public function apply(object $user, string $type, array $payload, string $opId): array;
}

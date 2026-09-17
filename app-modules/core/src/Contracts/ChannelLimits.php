<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * The plan limits a channel runs under (BE-T12, EP-AD-055).
 *
 * Resolution order is Tenancy's business, not the caller's: an unexpired override, then
 * the channel's own limits row, then its plan, then the platform defaults. An expired
 * temporary override falls out of that chain by comparison at read time, so it reverts
 * with no scheduled job having to run on time.
 *
 * Every limit is an integer. The keys are fixed: `users`, `warehouses`, `reps`, `skus`,
 * `storage_mb`.
 */
interface ChannelLimits
{
    public const KEYS = ['users', 'warehouses', 'reps', 'skus', 'storage_mb'];

    /** SKU cap for the channel. Kept for the callers that predate `cap()`. */
    public function skuCap(int $channelId): int;

    /**
     * The effective cap for one key.
     */
    public function cap(int $channelId, string $key): int;

    /**
     * Every effective cap at once, keyed by {@see self::KEYS}.
     *
     * @return array<string, int>
     */
    public function caps(int $channelId): array;

    /**
     * Refuse adding one more of `$key` when the channel already holds `$currentUsage`.
     *
     * Throws a `DomainException` carrying `plan_limit_exceeded` (423) whose details name
     * the limit that was hit — `{limit, max, used}` — so the client can say which one.
     */
    public function assertCanAdd(int $channelId, string $key, int $currentUsage): void;
}

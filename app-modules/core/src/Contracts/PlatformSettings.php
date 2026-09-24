<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

/**
 * Platform-wide settings, keyed by (group, key), one JSON value per pair (PA-11).
 *
 * Core owns the `platform_settings` table and its model. A module that reads or
 * writes a setting from outside Core — Integration's OTP channel switch, maintenance
 * flag and integration credentials (PA-15) — goes through this contract, never the
 * model (rule 1).
 */
interface PlatformSettings
{
    /**
     * The stored value, or null when the pair has never been written.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $group, string $key): ?array;

    /**
     * Write the value for a pair, creating it on first write.
     *
     * @param  array<string, mixed>  $value
     */
    public function put(string $group, string $key, array $value, ?int $updatedBy): void;
}

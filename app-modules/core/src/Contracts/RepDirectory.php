<?php

declare(strict_types=1);

namespace Modules\Core\Contracts;

interface RepDirectory
{
    public function exists(int $repUserId): bool;

    public function belongsToChannel(int $repUserId, int $channelId): bool;

    public function profileId(int $repUserId): ?int;

    public function userIdForProfile(int $profileId): ?int;

    /**
     * @return list<int>
     */
    public function zoneIdsForUser(int $repUserId): array;

    public function channelIdForUser(int $repUserId): ?int;

    public function displayName(int $repUserId): ?string;

    public function phone(int $repUserId): ?string;
}

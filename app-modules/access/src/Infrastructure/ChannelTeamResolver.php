<?php

declare(strict_types=1);

namespace Modules\Access\Infrastructure;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

final class ChannelTeamResolver implements PermissionsTeamResolver
{
    protected int|string|null $teamId = 0;

    public function setPermissionsTeamId(int|string|Model|null $id): void
    {
        if ($id instanceof Model) {
            $id = $id->getKey();
        }

        $this->teamId = $id ?? 0;
    }

    public function getPermissionsTeamId(): int|string|null
    {
        return $this->teamId ?? 0;
    }
}

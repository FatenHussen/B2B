<?php

declare(strict_types=1);

namespace Modules\Access\Console;

use Illuminate\Console\Command;
use Modules\Access\Domain\PermissionCatalog;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final class SyncPermissionsCommand extends Command
{
    protected $signature = 'access:sync';

    protected $description = 'Sync Spatie permissions from PermissionCatalog';

    public function handle(PermissionRegistrar $registrar): int
    {
        $registrar->forgetCachedPermissions();
        $registrar->setPermissionsTeamId(0);

        $count = 0;
        foreach (PermissionCatalog::all() as $code => $row) {
            Permission::findOrCreate($code, PermissionCatalog::guardForSystem($row['system']));
            $count++;
        }

        $registrar->forgetCachedPermissions();
        $this->info("Synced {$count} catalog permissions.");

        return self::SUCCESS;
    }
}

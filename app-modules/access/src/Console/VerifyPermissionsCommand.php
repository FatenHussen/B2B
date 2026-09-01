<?php

declare(strict_types=1);

namespace Modules\Access\Console;

use Illuminate\Console\Command;
use Modules\Access\Domain\PermissionCatalog;

final class VerifyPermissionsCommand extends Command
{
    protected $signature = 'access:verify';

    protected $description = 'Fail if a route permission: middleware code is missing from PermissionCatalog';

    public function handle(): int
    {
        $files = glob(base_path('app-modules/*/routes/api.php')) ?: [];
        $missing = [];

        foreach ($files as $file) {
            $contents = (string) file_get_contents($file);
            if (preg_match_all("/permission:([a-z0-9_.*|]+)/", $contents, $matches) === false) {
                continue;
            }
            foreach ($matches[1] as $raw) {
                foreach (explode('|', $raw) as $code) {
                    if ($code !== '' && ! PermissionCatalog::exists($code)) {
                        $missing[] = $code.' ('.$file.')';
                    }
                }
            }
        }

        if ($missing !== []) {
            $this->error('Permissions used in routes but missing from catalog:');
            foreach (array_unique($missing) as $line) {
                $this->line(' - '.$line);
            }

            return self::FAILURE;
        }

        $this->info('All route permission codes exist in PermissionCatalog.');

        return self::SUCCESS;
    }
}

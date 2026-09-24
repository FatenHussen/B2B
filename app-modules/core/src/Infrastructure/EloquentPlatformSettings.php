<?php

declare(strict_types=1);

namespace Modules\Core\Infrastructure;

use Modules\Core\Contracts\PlatformSettings;
use Modules\Core\Domain\Models\PlatformSetting;

final class EloquentPlatformSettings implements PlatformSettings
{
    public function get(string $group, string $key): ?array
    {
        $value = PlatformSetting::query()
            ->where('group', $group)
            ->where('key', $key)
            ->value('value');

        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        return is_array($value) ? $value : null;
    }

    public function put(string $group, string $key, array $value, ?int $updatedBy): void
    {
        PlatformSetting::query()->updateOrCreate(
            ['group' => $group, 'key' => $key],
            ['value' => $value, 'updated_by' => $updatedBy],
        );
    }
}

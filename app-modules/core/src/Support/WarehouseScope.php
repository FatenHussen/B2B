<?php

declare(strict_types=1);

namespace Modules\Core\Support;

final class WarehouseScope
{
    protected static ?int $warehouseId = null;

    public static function set(?int $id): void
    {
        static::$warehouseId = $id;
    }

    public static function currentId(): ?int
    {
        return static::$warehouseId;
    }

    public static function check(): bool
    {
        return static::$warehouseId !== null;
    }

    public static function forget(): void
    {
        static::$warehouseId = null;
    }
}

<?php

declare(strict_types=1);

namespace Modules\Core\Support;

final class WarehouseScope
{
    protected static ?int $warehouseId = null;

    public static function set(?int $id): void
    {
        self::$warehouseId = $id;
    }

    public static function currentId(): ?int
    {
        return self::$warehouseId;
    }

    public static function check(): bool
    {
        return self::$warehouseId !== null;
    }

    public static function forget(): void
    {
        self::$warehouseId = null;
    }
}

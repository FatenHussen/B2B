<?php

namespace Modules\Core\Support;

class Tenant
{
    protected static ?int $channelId = null;

    protected static bool $unscoped = false;

    public static function set(?int $id): void
    {
        static::$channelId = $id;
    }

    public static function currentId(): ?int
    {
        return static::$unscoped ? null : static::$channelId;
    }

    public static function check(): bool
    {
        return static::currentId() !== null;
    }

    public static function isUnscoped(): bool
    {
        return static::$unscoped;
    }

    public static function forget(): void
    {
        static::$channelId = null;
        static::$unscoped = false;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withoutScope(callable $callback): mixed
    {
        $previous = static::$unscoped;
        static::$unscoped = true;

        try {
            return $callback();
        } finally {
            static::$unscoped = $previous;
        }
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function as(?int $id, callable $callback): mixed
    {
        $previous = static::$channelId;
        static::$channelId = $id;

        try {
            return $callback();
        } finally {
            static::$channelId = $previous;
        }
    }
}

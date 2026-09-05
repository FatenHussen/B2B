<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Resolving class names the way PHP does, for the architecture rules that read source
 * rather than reflection.
 *
 * `\x5c` inside these patterns is a single backslash. Written that way the namespace
 * separators stay readable instead of turning into a wall of escapes.
 */
final class ModuleNames
{
    public const SEPARATOR = '\\';

    /** The module a fully qualified name belongs to, or null when it is not a module class. */
    public static function owning(string $class): ?string
    {
        return preg_match('/^Modules\x5c([A-Za-z0-9_]+)\x5c/', $class, $matched) === 1
            ? $matched[1]
            : null;
    }

    /** The namespace declared by a source file, or null when it declares none. */
    public static function namespaceOf(string $code): ?string
    {
        return preg_match('/^\s*namespace\s+([^;]+);/m', $code, $matched) === 1
            ? trim($matched[1])
            : null;
    }

    /**
     * alias => fully qualified name, for every `use` statement in $code.
     *
     * @return array<string, string>
     */
    public static function imports(string $code): array
    {
        preg_match_all(
            '/^\s*use\s+([A-Za-z_][\w\x5c]*)(?:\s+as\s+([A-Za-z_]\w*))?\s*;/mi',
            $code,
            $uses,
            PREG_SET_ORDER,
        );

        $aliases = [];

        foreach ($uses as $use) {
            $target = $use[1];
            $at = strrpos($target, self::SEPARATOR);
            $short = $at === false ? $target : substr($target, $at + 1);
            $aliases[$use[2] ?? $short] = $target;
        }

        return $aliases;
    }

    /**
     * Resolve a class name the way PHP does: rooted, then by import, then relative.
     *
     * @param  array<string, string>  $aliases
     */
    public static function resolve(string $name, bool $rooted, string $namespace, array $aliases): string
    {
        if ($rooted) {
            return $name;
        }

        $parts = explode(self::SEPARATOR, $name);
        $head = array_shift($parts);

        if (isset($aliases[$head])) {
            return $aliases[$head].($parts === [] ? '' : self::SEPARATOR.implode(self::SEPARATOR, $parts));
        }

        return $namespace.self::SEPARATOR.$name;
    }

    /**
     * $code with every comment and docblock blanked out, so a rule that scans text does
     * not fail on a `@param Modules\Other\...` annotation that compiles to nothing.
     */
    public static function withoutComments(string $code): string
    {
        $stripped = '';

        foreach (token_get_all($code) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                $stripped .= str_repeat("\n", substr_count($token[1], "\n"));

                continue;
            }

            $stripped .= is_array($token) ? $token[1] : $token;
        }

        return $stripped;
    }
}

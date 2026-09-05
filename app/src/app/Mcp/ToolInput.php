<?php

declare(strict_types=1);

namespace App\Mcp;

/**
 * Reads a validated tool argument as the type its rule promised. A JSON
 * client sends typed values, but a validated array is still `mixed` per
 * key, and each reader here narrows one key to one type or its default.
 */
final class ToolInput
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  array<string, mixed>  $input
     */
    public static function integer(array $input, string $key, int $default): int
    {
        $value = $input[$key] ?? null;

        return is_int($value) || (is_string($value) && is_numeric($value)) ? (int) $value : $default;
    }

    /**
     * An absent or blank string is null.
     *
     * @param  array<string, mixed>  $input
     */
    public static function string(array $input, string $key): ?string
    {
        $value = $input[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public static function boolean(array $input, string $key): bool
    {
        return filter_var($input[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
    }
}

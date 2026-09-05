<?php

declare(strict_types=1);

namespace App\Logging\Admin;

/**
 * A request's root `http.request` `data` JSON, decoded once for the two
 * places that read fields off it: the grouped list row
 * (`LogRowQuery::summarizeRequestGroup`) and the story header
 * (`LogStoryHeader::of`). The mirror invariant means a line can be stored
 * with `data` that never parses, so unparsable text answers no fields
 * rather than throwing.
 */
final class LogRequestData
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return array<array-key, mixed>
     */
    public static function decode(?string $text): array
    {
        if ($text === null) {
            return [];
        }

        $decoded = json_decode($text, associative: true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function stringField(array $data, string $field): ?string
    {
        $value = $data[$field] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function intField(array $data, string $field): ?int
    {
        $value = $data[$field] ?? null;

        return is_int($value) ? $value : null;
    }
}

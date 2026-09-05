<?php

declare(strict_types=1);

namespace App\Logging\Admin;

/**
 * Stored `data`/`error` JSON text, indented for a person to read in the
 * viewer's disclosure blocks. The mirror invariant means a line can be
 * stored with text that never parses, so unparsable text renders as it
 * stands rather than throwing.
 */
final class LogJson
{
    private function __construct() {} // @codeCoverageIgnore

    public static function pretty(string $text): string
    {
        $decoded = json_decode($text);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $text;
        }

        $encoded = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return $encoded === false ? $text : $encoded;
    }
}

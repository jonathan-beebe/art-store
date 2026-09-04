<?php

declare(strict_types=1);

namespace Tests;

/**
 * A link's query string, decoded into an array — what a test asserts a
 * rendered `href` carries, in place of matching the URL as literal text. A
 * real, Composer-autoloaded class, so every sidecar reads the same one
 * whatever order Pest requires the files in.
 */
final class QueryString
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return array<int|string, mixed>
     */
    public static function of(string $url): array
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $params);

        return $params;
    }
}

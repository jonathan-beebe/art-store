<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * Whether one response is a page view worth rolling up. A GET that answered
 * with a page is traffic; a form POST, a redirect, a JSON fragment, and a
 * failed request are not — counting them would make `/admin/stats` read as
 * hits on endpoints nobody browsed.
 */
final class PageViewCountability
{
    private function __construct() {} // @codeCoverageIgnore

    public static function isCountable(string $method, int $statusCode, ?string $contentType): bool
    {
        $isGet = strtoupper($method) === 'GET';
        $isSuccess = $statusCode >= 200 && $statusCode < 300;
        $isHtml = $contentType !== null && str_contains(strtolower($contentType), 'text/html');

        return $isGet && $isSuccess && $isHtml;
    }
}

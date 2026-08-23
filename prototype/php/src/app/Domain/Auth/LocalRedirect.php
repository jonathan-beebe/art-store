<?php

declare(strict_types=1);

namespace App\Domain\Auth;

final class LocalRedirect
{
    private function __construct() {} // @codeCoverageIgnore

    public static function resolve(?string $requested, string $fallback, string $origin): string
    {
        return self::keepIfLocal($requested, $origin) ?? $fallback;
    }

    /**
     * A destination reaches us through a form field and rides on a magic link,
     * so anything that could send the visitor off this site — or split the
     * response header — is dropped rather than carried.
     *
     * @return string|null null when the target does not stay on this site
     */
    public static function keepIfLocal(?string $requested, string $origin): ?string
    {
        $target = trim((string) $requested);

        if ($target === '' || preg_match('/[\x00-\x1F\x7F]/', $target) === 1) {
            return null;
        }

        if (str_starts_with($target, '/') && ! str_starts_with($target, '//') && ! str_starts_with($target, '/\\')) {
            return $target;
        }

        if ($target === $origin || str_starts_with($target, $origin.'/')) {
            return $target;
        }

        return null;
    }
}

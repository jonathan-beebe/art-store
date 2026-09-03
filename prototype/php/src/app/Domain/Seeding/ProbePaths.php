<?php

declare(strict_types=1);

namespace App\Domain\Seeding;

/**
 * The credential and admin paths a real prober scans for, each with the
 * status a server that names no such route answers with — 404 for every
 * path this application never registers, 302 for `/admin`, which exists
 * and redirects an unauthenticated request to the admin login.
 * `App\Console\Commands\SeedActivity` cycles this list to script the
 * prober bad actor; it never reaches a real route, so no controller here
 * needs to exist for any of these paths to answer.
 */
final class ProbePaths
{
    private const int NOT_FOUND = 404;

    private const int FOUND_REDIRECT = 302;

    /**
     * path => status. Ordered the way a real scanning tool works down a
     * wordlist: secrets first, then source control, then well-known CMS
     * and framework admin surfaces.
     *
     * @var array<string, int>
     */
    private const array PATHS = [
        '/.env' => self::NOT_FOUND,
        '/.env.backup' => self::NOT_FOUND,
        '/.env.production' => self::NOT_FOUND,
        '/.aws/credentials' => self::NOT_FOUND,
        '/.git/config' => self::NOT_FOUND,
        '/.git/HEAD' => self::NOT_FOUND,
        '/.ssh/id_rsa' => self::NOT_FOUND,
        '/config.json' => self::NOT_FOUND,
        '/config.php' => self::NOT_FOUND,
        '/wp-login.php' => self::NOT_FOUND,
        '/wp-admin/' => self::NOT_FOUND,
        '/wp-content/debug.log' => self::NOT_FOUND,
        '/xmlrpc.php' => self::NOT_FOUND,
        '/phpmyadmin/' => self::NOT_FOUND,
        '/phpinfo.php' => self::NOT_FOUND,
        '/administrator/' => self::NOT_FOUND,
        '/admin' => self::FOUND_REDIRECT,
        '/actuator/health' => self::NOT_FOUND,
        '/actuator/env' => self::NOT_FOUND,
        '/api/v1/users' => self::NOT_FOUND,
        '/api/v1/login' => self::NOT_FOUND,
        '/server-status' => self::NOT_FOUND,
        '/.well-known/security.txt' => self::NOT_FOUND,
        '/vendor/phpunit/phpunit/src/Util/PHP/eval-stdin.php' => self::NOT_FOUND,
        '/telescope' => self::NOT_FOUND,
        '/_profiler/phpinfo' => self::NOT_FOUND,
    ];

    private function __construct() {} // @codeCoverageIgnore

    /**
     * Every path this list names, in fixed order.
     *
     * @return list<string>
     */
    public static function paths(): array
    {
        return array_keys(self::PATHS);
    }

    public static function statusFor(string $path): int
    {
        return self::PATHS[$path] ?? self::NOT_FOUND;
    }
}

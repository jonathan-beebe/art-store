<?php

declare(strict_types=1);

namespace App\Domain\Auth;

/**
 * The shape of a plaintext MCP api key and the digest the database keeps
 * of it (docs/spec.md §5 "MCP endpoint"). The prefix makes a leaked key
 * recognisable to a secret scanner and to a person reading a config file;
 * the digest is what a row stores, so a leaked row cannot be replayed as
 * a key, the same rule `MagicLinkToken` follows.
 */
final class ApiKeyToken
{
    public const string PREFIX = 'artstore_';

    public const int SECRET_LENGTH = 40;

    private const string PLAINTEXT = '/\Aartstore_[A-Za-z0-9]{40}\z/';

    private function __construct() {} // @codeCoverageIgnore

    public static function isWellFormed(string $token): bool
    {
        return preg_match(self::PLAINTEXT, $token) === 1;
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}

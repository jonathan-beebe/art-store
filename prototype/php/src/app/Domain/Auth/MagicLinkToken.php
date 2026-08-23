<?php

declare(strict_types=1);

namespace App\Domain\Auth;

final class MagicLinkToken
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * Only the digest is stored, so a leaked database row cannot be replayed
     * as a link.
     */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}

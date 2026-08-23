<?php

declare(strict_types=1);

namespace App\Domain\Auth;

final class EmailNormalizer
{
    private function __construct() {} // @codeCoverageIgnore

    /**
     * Addresses arrive from forms and from magic-link rows; both sides compare
     * against the normalized form so one person never ends up with two accounts.
     */
    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}

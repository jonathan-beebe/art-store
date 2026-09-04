<?php

declare(strict_types=1);

namespace App\Domain\Store;

use DateInterval;
use DateTimeImmutable;

/**
 * How long a store's old address keeps forwarding. A link shared before a
 * rename lands on the store for thirty days; after that the address is as
 * unknown as one no store ever held.
 */
final class RetiredSlugWindow
{
    public const int DAYS = 30;

    private function __construct() {} // @codeCoverageIgnore

    /**
     * The oldest retirement a redirect still answers for. An address
     * retired before this moment answers 404.
     */
    public static function opensAt(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->sub(new DateInterval('P'.self::DAYS.'D'));
    }

    public static function stillForwards(DateTimeImmutable $retiredAt, DateTimeImmutable $now): bool
    {
        return $retiredAt >= self::opensAt($now);
    }
}

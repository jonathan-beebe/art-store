<?php

declare(strict_types=1);

namespace App\Domain\Auth;

use DateTimeInterface;

enum MagicLinkStatus
{
    case Usable;
    case Expired;
    case Consumed;

    public static function of(DateTimeInterface $expiresAt, ?DateTimeInterface $consumedAt, DateTimeInterface $now): self
    {
        if ($consumedAt !== null) {
            return self::Consumed;
        }

        if ($now >= $expiresAt) {
            return self::Expired;
        }

        return self::Usable;
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Payments;

/**
 * The two ends a card decision settles to. Narrower than {@see CardDecision}
 * (which also carries the last four digits and a decline reason), so a
 * branch on this type is exhaustive with exactly two arms.
 */
enum PaymentOutcome
{
    case Approved;
    case Declined;

    public static function fromCardDecision(CardDecision $decision): self
    {
        return $decision->isApproved ? self::Approved : self::Declined;
    }
}

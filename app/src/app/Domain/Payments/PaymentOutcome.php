<?php

declare(strict_types=1);

namespace App\Domain\Payments;

/**
 * The two ends a card decision settles to: Approved or Declined. A branch
 * on this type is exhaustive with exactly two arms. {@see CardDecision}
 * carries more: the last four digits and a decline reason.
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

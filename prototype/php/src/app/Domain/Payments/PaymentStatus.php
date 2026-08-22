<?php

namespace App\Domain\Payments;

enum PaymentStatus: string
{
    case Approved = 'approved';
    case Declined = 'declined';

    public static function fromCardDecision(CardDecision $decision): self
    {
        return $decision->isApproved ? self::Approved : self::Declined;
    }
}

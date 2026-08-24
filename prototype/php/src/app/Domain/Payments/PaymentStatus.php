<?php

declare(strict_types=1);

namespace App\Domain\Payments;

enum PaymentStatus: string
{
    case Approved = 'approved';
    case Declined = 'declined';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public static function fromCardDecision(CardDecision $decision): self
    {
        return $decision->isApproved ? self::Approved : self::Declined;
    }
}

<?php

namespace App\Domain\Payments;

enum DeclineReason: string
{
    case GenericDecline = 'generic_decline';
    case InsufficientFunds = 'insufficient_funds';
    case InvalidCardNumber = 'invalid_card_number';

    public function message(): string
    {
        return match ($this) {
            self::GenericDecline => 'Your card was declined.',
            self::InsufficientFunds => 'Your card has insufficient funds.',
            self::InvalidCardNumber => 'That card number is not valid.',
        };
    }
}

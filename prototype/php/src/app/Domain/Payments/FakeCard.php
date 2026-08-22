<?php

namespace App\Domain\Payments;

final class FakeCard
{
    private const APPROVED_NUMBER = '4242424242424242';

    private const DECLINED_NUMBERS = [
        '4000000000000002' => DeclineReason::GenericDecline,
        '4000000000009995' => DeclineReason::InsufficientFunds,
    ];

    public static function decide(string $number): CardDecision
    {
        $digits = preg_replace('/\D/', '', $number);
        $lastFour = substr($digits, -4);

        if ($digits === self::APPROVED_NUMBER) {
            return CardDecision::approved($lastFour);
        }

        return CardDecision::declined($lastFour, self::DECLINED_NUMBERS[$digits] ?? DeclineReason::InvalidCardNumber);
    }
}

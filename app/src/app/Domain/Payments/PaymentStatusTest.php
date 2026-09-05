<?php

declare(strict_types=1);

namespace App\Domain\Payments;

it('records payment status from a card decision', function (CardDecision $decision, PaymentStatus $expected): void {
    expect(PaymentStatus::fromCardDecision($decision))->toBe($expected);
})->with([
    'an approved card records an approved payment' => [CardDecision::approved('4242'), PaymentStatus::Approved],
    'a declined card records a declined payment' => [CardDecision::declined('0002', DeclineReason::GenericDecline), PaymentStatus::Declined],
]);

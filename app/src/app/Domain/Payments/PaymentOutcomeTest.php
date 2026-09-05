<?php

declare(strict_types=1);

namespace App\Domain\Payments;

it('is approved for an approved card decision', function (): void {
    expect(PaymentOutcome::fromCardDecision(CardDecision::approved('4242')))->toBe(PaymentOutcome::Approved);
});

it('is declined for a declined card decision', function (): void {
    expect(PaymentOutcome::fromCardDecision(CardDecision::declined('0002', DeclineReason::GenericDecline)))
        ->toBe(PaymentOutcome::Declined);
});

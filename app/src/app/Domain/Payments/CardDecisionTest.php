<?php

declare(strict_types=1);

namespace App\Domain\Payments;

it('carries no decline reason on approval', function (): void {
    $decision = CardDecision::approved('4242');

    expect($decision->isApproved)->toBeTrue()
        ->and($decision->lastFour)->toBe('4242')
        ->and($decision->declineReason)->toBeNull();
});

it('carries its reason on decline', function (): void {
    $decision = CardDecision::declined('9995', DeclineReason::InsufficientFunds);

    expect($decision->isApproved)->toBeFalse()
        ->and($decision->lastFour)->toBe('9995')
        ->and($decision->declineReason)->toBe(DeclineReason::InsufficientFunds);
});

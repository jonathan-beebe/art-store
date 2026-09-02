<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

it('names the events-per-hour threshold', function (): void {
    expect(ActorVelocity::THRESHOLD_PER_HOUR)->toBe(100);
});

it('flags a peak at or above the threshold', function (): void {
    expect(ActorVelocity::flags(100))->toBeTrue()
        ->and(ActorVelocity::flags(412))->toBeTrue();
});

it('leaves a peak below the threshold unflagged', function (): void {
    expect(ActorVelocity::flags(99))->toBeFalse()
        ->and(ActorVelocity::flags(0))->toBeFalse();
});

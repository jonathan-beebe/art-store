<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

it('names the actor-kind segmented control\'s three values', function (): void {
    expect(array_column(ActorKindFilter::cases(), 'value'))->toBe(['all', 'anonymous', 'verified']);
});

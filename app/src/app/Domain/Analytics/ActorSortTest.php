<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

it('names the all-actors sort control\'s two values', function (): void {
    expect(array_column(ActorSort::cases(), 'value'))->toBe(['active', 'recent']);
});

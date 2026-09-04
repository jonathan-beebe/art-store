<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\FunnelDefinition;
use App\Models\Funnel;

it('seeds the storefront funnel with FunnelDefinition::storefront()\'s own step list', function (): void {
    $this->seed(FunnelSeeder::class);

    $funnel = Funnel::query()->where('slug', 'storefront')->sole();
    $expectedSteps = array_map(fn (AnalyticsEventName $name): string => $name->value, FunnelDefinition::storefront()->steps);

    expect($funnel->name)->toBe('Storefront')
        ->and($funnel->steps)->toBe($expectedSteps)
        ->and($funnel->position)->toBe(1);
});

it('changes nothing on a second run', function (): void {
    $this->seed(FunnelSeeder::class);
    $this->seed(FunnelSeeder::class);

    expect(Funnel::query()->where('slug', 'storefront')->count())->toBe(1);
});

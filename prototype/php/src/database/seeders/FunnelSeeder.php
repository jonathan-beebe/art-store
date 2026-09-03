<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\FunnelDefinition;
use App\Models\Funnel;
use Illuminate\Database\Seeder;

/**
 * The built-in storefront funnel, seeded as a `Funnel` row like any other
 * an admin defines. Runs unconditionally alongside `AdminSeeder` — the
 * analytics home reads it as one of its tiles, not as demo data a seeded
 * database might already carry.
 */
class FunnelSeeder extends Seeder
{
    public function run(): void
    {
        Funnel::firstOrCreate(
            ['slug' => 'storefront'],
            [
                'name' => 'Storefront',
                'steps' => array_map(
                    fn (AnalyticsEventName $name): string => $name->value,
                    FunnelDefinition::storefront()->steps,
                ),
                'position' => 1,
            ],
        );
    }
}

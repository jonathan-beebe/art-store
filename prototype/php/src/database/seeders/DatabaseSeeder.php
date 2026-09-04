<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Logging\StoryEvent;
use App\Models\Seller;
use App\Support\Story;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $seeders = [
            AdminSeeder::class,
            FunnelSeeder::class,
            TaxonomySeeder::class,
            SellerSeeder::class,
            ListingSeeder::class,
            CustomerSeeder::class,
            OrderHistorySeeder::class,
            MessagingSeeder::class,
            WizardingSellerSeeder::class,
            ConfiguratorArchetypeSeeder::class,
            ListingImageSeeder::class,
            StoreProfileSeeder::class,
            FulfillmentFlowSeeder::class,
        ];

        $story = Story::for(StoryEvent::SeedRun)->will('seeding the demo data', [
            'seeder_count' => count($seeders),
        ]);

        // The admins and the built-in funnel re-run safely (firstOrCreate);
        // the demo half only fits an empty schema, so a database that
        // already holds a seller keeps what it has. Deploys chain `db:seed`
        // on every boot and rely on this.
        $this->call(AdminSeeder::class);
        $this->call(FunnelSeeder::class);

        if (Seller::query()->exists()) {
            $story->did('demo data already seeded, skipping', ['skipped' => true]);

            return;
        }

        $this->call(array_slice($seeders, 2));

        $story->did('seeded the demo data', ['seeder_count' => count($seeders)]);
    }
}

<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Logging\StoryEvent;
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
            SellerSeeder::class,
            ListingSeeder::class,
            CustomerSeeder::class,
            OrderHistorySeeder::class,
            MessagingSeeder::class,
        ];

        $story = Story::for(StoryEvent::SeedRun)->will('seeding the demo data', [
            'seeder_count' => count($seeders),
        ]);

        $this->call($seeders);

        $story->did('seeded the demo data', ['seeder_count' => count($seeders)]);
    }
}

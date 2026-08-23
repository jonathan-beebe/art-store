<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Listings\RecordListingEvent;
use App\Domain\Listings\ListingEventType;
use App\Models\Customer;
use App\Models\Favorite;
use App\Models\Listing;
use DateTimeImmutable;
use Illuminate\Database\Seeder;

/**
 * One verified customer with browsing history: views on several listings and
 * three favorites, so the storefront's favorites page has content on first
 * load.
 */
class CustomerSeeder extends Seeder
{
    public const CASEY_EMAIL = 'casey@example.com';

    private const FAVORITE_TITLES = [
        'Woodfired Vase, Tall',
        'Quarry at First Light',
        'Handwoven Mohair Throw',
    ];

    private const VIEWED_TITLES = [
        'Woodfired Vase, Tall',
        'Quarry at First Light',
        'Handwoven Mohair Throw',
        'Ash-Glazed Tea Bowl',
        'Kitchen Table, Late Morning',
        'Standing Figure in Reclaimed Oak',
    ];

    public function run(): void
    {
        $customer = Customer::create([
            'email' => self::CASEY_EMAIL,
            'name' => 'Casey Whitfield',
            'email_verified_at' => new DateTimeImmutable('2026-06-01 00:00:00'),
        ]);

        $this->recordViews($customer);
        $this->recordFavorites($customer);
    }

    private function recordViews(Customer $customer): void
    {
        $recordListingEvent = app(RecordListingEvent::class);
        $viewedAt = new DateTimeImmutable('2026-07-01 09:00:00');

        foreach (self::VIEWED_TITLES as $title) {
            $recordListingEvent($this->listing($title), $customer->id, ListingEventType::View, $viewedAt);
            $viewedAt = $viewedAt->modify('+1 minute');
        }
    }

    private function recordFavorites(Customer $customer): void
    {
        $recordListingEvent = app(RecordListingEvent::class);
        $favoritedAt = new DateTimeImmutable('2026-07-01 09:10:00');

        foreach (self::FAVORITE_TITLES as $title) {
            $listing = $this->listing($title);

            Favorite::create(['customer_id' => $customer->id, 'listing_id' => $listing->id]);
            $recordListingEvent($listing, $customer->id, ListingEventType::Favorite, $favoritedAt);
            $favoritedAt = $favoritedAt->modify('+1 minute');
        }
    }

    private function listing(string $title): Listing
    {
        return Listing::where('title', $title)->firstOrFail();
    }
}

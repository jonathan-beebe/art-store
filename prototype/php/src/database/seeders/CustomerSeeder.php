<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Favorites\ToggleFavorite;
use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Analytics\AnalyticsEventName;
use App\Domain\Listings\ListingViewCollapse;
use App\Models\Customer;
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
    public const HERMIONE_EMAIL = 'hermione@example.com';

    public const LUNA_EMAIL = 'luna@example.com';

    private const FAVORITE_TITLES = [
        'Divination Tower Vase, Tall',
        'The Orchard at First Light',
        'House Scarf Throw, Scarlet and Gold',
    ];

    private const VIEWED_TITLES = [
        'Divination Tower Vase, Tall',
        'The Orchard at First Light',
        'House Scarf Throw, Scarlet and Gold',
        'Burrow Kitchen Tea Bowl',
        'Gryffindor Common Room, Late Morning',
        'Garden Gnome in Reclaimed Oak',
    ];

    public function run(): void
    {
        $customer = Customer::create([
            'email' => self::HERMIONE_EMAIL,
            'name' => 'Hermione Granger',
            'email_verified_at' => new DateTimeImmutable('2026-06-01 00:00:00'),
        ]);

        $this->recordViews($customer);
        $this->recordFavorites($customer);

        // A second verified customer, so a listing question and a support
        // thread each have someone besides Hermione to belong to.
        Customer::create([
            'email' => self::LUNA_EMAIL,
            'name' => 'Luna Lovegood',
            'email_verified_at' => new DateTimeImmutable('2026-06-02 00:00:00'),
        ]);
    }

    private function recordViews(Customer $customer): void
    {
        $analytics = app(Analytics::class);
        $viewedAt = new DateTimeImmutable('2026-07-01 09:00:00');

        foreach (self::VIEWED_TITLES as $title) {
            $listing = $this->listing($title);
            $analytics->recordEvent(AnalyticsEvent::forListing(
                AnalyticsEventName::ListingView,
                $listing->id,
                $customer->id,
                $viewedAt,
                ListingViewCollapse::dedupeKey($listing->id, $customer->id, $viewedAt),
            ));
            $viewedAt = $viewedAt->modify('+1 minute');
        }
    }

    private function recordFavorites(Customer $customer): void
    {
        $toggleFavorite = app(ToggleFavorite::class);
        $favoritedAt = new DateTimeImmutable('2026-07-01 09:10:00');

        foreach (self::FAVORITE_TITLES as $title) {
            $toggleFavorite($customer, $this->listing($title), $favoritedAt);
            $favoritedAt = $favoritedAt->modify('+1 minute');
        }
    }

    private function listing(string $title): Listing
    {
        return Listing::where('title', $title)->firstOrFail();
    }
}

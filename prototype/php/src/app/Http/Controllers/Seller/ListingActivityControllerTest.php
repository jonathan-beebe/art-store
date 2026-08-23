<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Listings\RecordListingEvent;
use App\Actions\Orders\FinalizeOrder;
use App\Domain\Listings\ListingEventType;
use App\Domain\Reports\DailyActivity;
use App\Models\Listing;
use App\Models\Seller;
use DateTimeImmutable;
use Tests\CommerceTestCase;

final class ListingActivityControllerTest extends CommerceTestCase
{
    public function test_it_sends_a_signed_out_visitor_to_the_sign_in_page(): void
    {
        $listing = $this->listing($this->seller());

        $this->get("/seller/listings/{$listing->id}")->assertRedirect(route('auth.seller.login'));
    }

    public function test_it_renders_the_activity_page(): void
    {
        $seller = $this->seller();
        $listing = $this->listing($seller, ['title' => 'Harbour at Dusk']);

        $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}");

        $response->assertOk();
        $response->assertSee('Harbour at Dusk');
    }

    public function test_it_hides_another_sellers_listing(): void
    {
        $listing = $this->listing($this->seller('Other Studio'));

        $response = $this->actingAs($this->seller(), 'seller')->get("/seller/listings/{$listing->id}");

        $response->assertNotFound();
    }

    public function test_it_totals_the_events_of_the_listing(): void
    {
        $seller = $this->seller();
        $listing = $this->recordedActivity($seller);

        $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}");

        $response->assertViewHas('listing', function (Listing $listing): bool {
            return $listing->views_count === 2 && $listing->favorites_count === 1 && $listing->cart_adds_count === 1;
        });
    }

    public function test_it_breaks_the_last_fourteen_days_down_by_day(): void
    {
        $seller = $this->seller();
        $listing = $this->listing($seller);

        $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}");

        $response->assertViewHas('days', fn (array $days): bool => count($days) === 14);
        $response->assertViewHas('windowDays', 14);
    }

    public function test_it_counts_todays_events_on_todays_row(): void
    {
        $seller = $this->seller();
        $listing = $this->listing($seller);
        app(RecordListingEvent::class)($listing, null, ListingEventType::View, new DateTimeImmutable(now()->toDateTimeString()));

        $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}");

        $response->assertViewHas('days', function (array $days): bool {
            $today = $days[13];

            return $today instanceof DailyActivity && $today->views === 1;
        });
    }

    public function test_it_leaves_events_older_than_the_window_off_the_breakdown(): void
    {
        $seller = $this->seller();
        $listing = $this->listing($seller);
        app(RecordListingEvent::class)($listing, null, ListingEventType::View, $this->moment('2020-01-01 09:00:00'));

        $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}");

        $response->assertViewHas('days', fn (array $days): bool => array_sum(
            array_map(fn (DailyActivity $day): int => $day->total(), $days),
        ) === 0);
    }

    public function test_it_lists_the_sales_of_the_listing(): void
    {
        $seller = $this->seller();
        $listing = $this->listing($seller, ['title' => 'Harbour at Dusk', 'quantity' => 3]);
        $order = $this->orderFor($this->verifiedCustomer(), $listing);
        app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));

        $response = $this->actingAs($seller, 'seller')->get("/seller/listings/{$listing->id}");

        $response->assertViewHas('sales', fn ($sales): bool => $sales->count() === 1);
        $response->assertSee("#{$order->id}");
    }

    private function recordedActivity(Seller $seller): Listing
    {
        $listing = $this->listing($seller);
        $recordListingEvent = app(RecordListingEvent::class);
        $recordListingEvent($listing, null, ListingEventType::View, $this->moment('2026-08-20 09:00:00'));
        $recordListingEvent($listing, null, ListingEventType::View, $this->moment('2026-08-20 10:00:00'));
        $recordListingEvent($listing, null, ListingEventType::Favorite, $this->moment('2026-08-20 11:00:00'));
        $recordListingEvent($listing, null, ListingEventType::CartAdd, $this->moment('2026-08-20 12:00:00'));

        return $listing;
    }
}

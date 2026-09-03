<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Cart\AddToCart;
use App\Actions\Listings\RemoveListing;
use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Analytics\ListingEventCounts;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Listings\ListingRemovalKind;
use App\Domain\Listings\ListingStatus;
use App\Models\Favorite;
use App\Models\Listing;
use App\Support\ListPaneWindow;

it('lists listings across every seller', function (): void {
    $this->listing($this->seller('Blue Kiln Studio'), ['title' => 'Nine Herons']);
    $this->listing($this->seller('Rye Press'), ['title' => 'Rye Harvest']);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/listings');

    $response->assertOk();
    $response->assertSee('Nine Herons');
    $response->assertSee('Rye Harvest');
    $response->assertSee('Blue Kiln Studio');
    $response->assertSee('Rye Press');
});

it('narrows the list to one status', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['title' => 'Nine Herons', 'status' => ListingStatus::ForSale]);
    $this->listing($seller, ['title' => 'Rye Harvest', 'status' => ListingStatus::Draft]);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/listings?status=draft');

    $response->assertOk();
    $response->assertSee('Rye Harvest');
    $response->assertDontSee('Nine Herons');
});

it('narrows the list to one seller', function (): void {
    $kiln = $this->seller('Blue Kiln Studio');
    $this->listing($kiln, ['title' => 'Nine Herons']);
    $this->listing($this->seller('Rye Press'), ['title' => 'Rye Harvest']);

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/listings?seller={$kiln->id}");

    $response->assertOk();
    $response->assertSee('Nine Herons');
    $response->assertDontSee('Rye Harvest');
});

it('reads an empty filter as every listing, the way the console submits it', function (string $query): void {
    $this->listing($this->seller('Blue Kiln Studio'), ['title' => 'Nine Herons', 'status' => ListingStatus::ForSale]);
    $this->listing($this->seller('Rye Press'), ['title' => 'Rye Harvest', 'status' => ListingStatus::Draft]);

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/listings?{$query}");

    $response->assertOk();
    $response->assertSee('Nine Herons');
    $response->assertSee('Rye Harvest');
})->with([
    'no filters at all' => '',
    'both filters empty' => 'status=&seller=',
    'a status that names nothing' => 'status=nonsense',
]);

it('narrows the list by removal state', function (): void {
    $seller = $this->seller();
    $this->listing($seller, ['title' => 'Nine Herons']);
    $removed = $this->listing($seller, ['title' => 'Rye Harvest']);
    app(RemoveListing::class)($removed, ListingRemovalKind::Temporary, 'Under review.');

    $removedOnly = $this->actingAs($this->admin(), 'admin')->get('/admin/listings?removed=removed');
    $removedOnly->assertSee('Rye Harvest')->assertDontSee('Nine Herons');

    $visibleOnly = $this->actingAs($this->admin(), 'admin')->get('/admin/listings?removed=visible');
    $visibleOnly->assertSee('Nine Herons')->assertDontSee('Rye Harvest');

    $any = $this->actingAs($this->admin(), 'admin')->get('/admin/listings?removed=any');
    $any->assertSee('Nine Herons')->assertSee('Rye Harvest');
});

it('says so when no listing matches the filters', function (): void {
    $this->listing($this->seller(), ['status' => ListingStatus::ForSale]);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/listings?status=archived');

    $response->assertOk();
    $response->assertSee('No listings.');
});

it('opens with a back link to the listing list, for below sm', function (): void {
    $listing = $this->listing($this->seller());

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/listings/{$listing->id}");

    $response->assertOk();
    expect($response->getContent())->toMatch('/<a href="'.preg_quote(route('admin.listings.index'), '/').'"[^>]*sm:hidden"[^>]*>\s*<svg[\s\S]*?<span>Listings<\/span>/');
});

it('shows one listing with its activity and sales', function (): void {
    $seller = $this->seller('Blue Kiln Studio');
    $listing = $this->listing($seller, ['title' => 'Nine Herons']);
    $this->mediumAttribute($listing, 'Oil on linen');
    $customer = $this->verifiedCustomer();
    Favorite::factory()->create(['customer_id' => $customer->id, 'listing_id' => $listing->id]);
    app(AddToCart::class)($this->cartFor($customer), $listing, 1, $this->moment('2026-08-20 08:00:00'));
    $order = $this->orderFor($this->verifiedCustomer(), $listing);

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/listings/{$listing->id}");

    $response->assertOk();
    $response->assertSee('Nine Herons');
    $response->assertSee('Oil on linen');
    $response->assertSee('Blue Kiln Studio');
    $response->assertSee($order->id);
});

it('totals the views and cart adds of the listing and counts the favorites that stand', function (): void {
    $listing = $this->listing($this->seller());
    $analytics = app(Analytics::class);
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, null, $this->moment('2026-08-20 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, null, $this->moment('2026-08-20 10:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingFavorite, $listing->id, null, $this->moment('2026-08-20 11:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingFavorite, $listing->id, null, $this->moment('2026-08-20 11:30:00')));
    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingCartAdd, $listing->id, null, $this->moment('2026-08-20 12:00:00')));
    $analytics->flush();
    Favorite::factory()->create(['customer_id' => $this->verifiedCustomer()->id, 'listing_id' => $listing->id]);

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/listings/{$listing->id}");

    $response->assertOk();
    $response->assertViewHas('eventCounts', function (ListingEventCounts $eventCounts): bool {
        return $eventCounts->views === 2
            && $eventCounts->cartAdds === 1;
    });
    $response->assertViewHas('listing', function (Listing $listing): bool {
        return $listing->favorites_count === 1;
    });
});

it('shows the list panes empty-detail prompt on the index route', function (): void {
    $this->listing($this->seller(), ['title' => 'Nine Herons']);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/listings');

    $response->assertOk();
    $response->assertSee('Choose a listing to see its details.');
});

it('renders the list pane beside the detail pane, with a sibling listing still on the list', function (): void {
    $seller = $this->seller('Blue Kiln Studio');
    $this->listing($seller, ['title' => 'Rye Harvest']);
    $viewed = $this->listing($seller, ['title' => 'Nine Herons']);

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/listings/{$viewed->id}");

    $response->assertOk();
    $response->assertSee('Nine Herons');
    $response->assertSee('Rye Harvest');
});

it('caps the list pane at the window size, however many listings exist', function (): void {
    Listing::factory()->count(ListPaneWindow::SIZE + 5)->create();

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/listings');

    $response->assertOk();
    expect(substr_count((string) $response->getContent(), 'data-pane-cell="'))->toBe(ListPaneWindow::SIZE);
});

it('keeps the viewed listing on the list pane even when it sorts outside the window', function (): void {
    $viewed = $this->listing($this->seller(), ['title' => 'Nine Herons', 'created_at' => now()->subDay()]);
    Listing::factory()->count(ListPaneWindow::SIZE + 5)->create();

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/listings/{$viewed->id}");

    $response->assertOk();
    $response->assertSee('Nine Herons');
    expect(substr_count((string) $response->getContent(), 'data-pane-cell="'))->toBe(ListPaneWindow::SIZE + 1);
});

it('says how many listings the list pane is not showing, linked to the full list', function (): void {
    Listing::factory()->count(ListPaneWindow::SIZE + 5)->create();

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/listings');

    $response->assertOk();
    $response->assertSee('Showing 50 of', false);
    $response->assertSee('href="'.route('admin.listings.index').'"', escape: false);
});

it('says nothing about a window that already holds every listing', function (): void {
    $this->listing($this->seller(), ['title' => 'Nine Herons']);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/listings');

    $response->assertOk();
    $response->assertDontSee('Showing');
});

it('shows the active removal and a lift button', function (): void {
    $listing = $this->listing($this->seller());
    app(RemoveListing::class)($listing, ListingRemovalKind::Temporary, 'Under review for a copyright claim.');

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/listings/{$listing->id}");

    $response->assertOk();
    $response->assertSee('Under review for a copyright claim.');
    $response->assertSee('Lift removal');
});

it('offers no lift button for a permanent removal', function (): void {
    $listing = $this->listing($this->seller());
    app(RemoveListing::class)($listing, ListingRemovalKind::Permanent, 'Counterfeit.');

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/listings/{$listing->id}");

    $response->assertOk();
    $response->assertSee('Counterfeit.');
    $response->assertDontSee('Lift removal');
});

it('says so on a listing nobody has bought', function (): void {
    $listing = $this->listing($this->seller());

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/listings/{$listing->id}");

    $response->assertOk();
    $response->assertSee('No sales yet.');
});

it('answers not found for a value that is not a listing id, the same as an unknown one', function (string $id): void {
    $this->actingAs($this->admin(), 'admin')->get("/admin/listings/{$id}")->assertNotFound();
})->with([
    'another table prefix' => 'sel_01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'a bare ULID' => '01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'a value of no shape at all' => 'nonsense',
    'a listing that does not exist' => 'lst_01J5X3M9A2K8YB7Q4R6T1V0WZE',
]);

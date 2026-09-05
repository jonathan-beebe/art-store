<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Escrow\RunWeeklyPayout;
use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Listings\ListingStatus;
use App\Models\Seller;
use App\Paging\ListPaneWindow;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

it('lists every seller', function (): void {
    $first = $this->seller('Blue Kiln Studio');
    $second = $this->seller('Rye Press');

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/sellers');

    $response->assertOk();
    $response->assertSee('Blue Kiln Studio');
    $response->assertSee('Rye Press');
});

it('folds every balance out of one read of the ledger, whatever the seller count', function (): void {
    $this->deliveredFulfillmentFor($this->seller('Blue Kiln Studio'), priceCents: 10000);
    $this->shippedFulfillmentFor($this->seller('Rye Press'), priceCents: 20000);
    $this->seller('Quiet Press');

    $ledgerReads = 0;
    DB::listen(function (QueryExecuted $query) use (&$ledgerReads): void {
        $ledgerReads += str_contains($query->sql, 'ledger_entries') ? 1 : 0;
    });

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/sellers');

    $response->assertOk();
    $response->assertSee('$90.00');
    $response->assertSee('$180.00');
    expect($ledgerReads)->toBe(1);
});

it('shows the seller\'s store name and visibility, unlinked, on the sellers list row', function (): void {
    $seller = $this->seller('Weasley Studio');
    $store = $this->storeFor($seller);
    $store->update(['published_at' => $this->moment('2026-08-01 09:00:00')]);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/sellers');

    $response->assertOk();
    $response->assertSee($store->name);
    $response->assertSee('Published');
    $response->assertDontSee('href="'.route('admin.analytics.stores.show', $store).'"', escape: false);
});

it('links the seller\'s store, with its visibility, on the detail page', function (): void {
    $seller = $this->seller('Weasley Studio');
    $store = $this->storeFor($seller);
    $store->update(['published_at' => $this->moment('2026-08-01 09:00:00')]);

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/sellers/{$seller->id}");

    $response->assertOk();
    $response->assertSee($store->name);
    $response->assertSee('Published');
    $response->assertSee('href="'.route('admin.analytics.stores.show', $store).'"', escape: false);
});

it('says a seller has no store yet, on the sellers list row', function (): void {
    $seller = $this->seller('Quiet Press');

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/sellers');

    $response->assertOk();
    $response->assertSee('No store yet');
});

it('says a seller has no store yet, on the detail page', function (): void {
    $seller = $this->seller('Quiet Press');

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/sellers/{$seller->id}");

    $response->assertOk();
    $response->assertSee('No store yet');
});

it('shows a funnel covering only the seller\'s own listings, last 30 days, linked to analytics', function (): void {
    $sellerOne = $this->seller('Blue Kiln Studio');
    $sellerTwo = $this->seller('Rye Press');
    $this->orderFor($this->verifiedCustomer(), $this->listing($sellerOne));
    $this->orderFor($this->verifiedCustomer(), $this->listing($sellerTwo));
    app(Analytics::class)->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/sellers/{$sellerOne->id}");

    $response->assertOk();
    $response->assertSee('Funnel, last 30 days');
    $response->assertSee('href="'.route('admin.analytics.index').'"', escape: false);
    $response->assertSeeInOrder(['Orders placed', '1']);
});

it('renders the seller page on a fixed number of queries however many events its listings\' funnel holds', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller);
    $analytics = app(Analytics::class);

    foreach (range(1, 15) as $i) {
        $customer = $this->verifiedCustomer();
        $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    }
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')
        ->expectsDatabaseQueryCount(14, 'sqlite')
        ->expectsDatabaseQueryCount(3, 'analytics')
        ->get("/admin/sellers/{$seller->id}");

    $response->assertOk();
});

it('shows one seller with listing and fulfillment counts', function (): void {
    $seller = $this->seller('Blue Kiln Studio');
    $this->listing($seller, ['status' => ListingStatus::ForSale]);
    $this->listing($seller, ['status' => ListingStatus::Draft]);
    $this->orderFor($this->verifiedCustomer(), $this->listing($seller));

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/sellers/{$seller->id}");

    $response->assertOk();
    $response->assertSee('Blue Kiln Studio');
});

it('offers a form to message the seller', function (): void {
    $seller = $this->seller('Blue Kiln Studio');

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/sellers/{$seller->id}");

    $response->assertSee('Message seller');
    $response->assertSee('action="'.route('admin.sellers.messages', $seller).'"', escape: false);
});

it('offers the sellers own fulfillments as the message forms order options', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller('Blue Kiln Studio'));

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/sellers/{$fulfillment->seller_id}");

    $response->assertSee("Order {$fulfillment->order_id}");
});

it('preselects the order an oversight thread carried in on the query string', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller('Blue Kiln Studio'));

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/sellers/{$fulfillment->seller_id}?fulfillment={$fulfillment->id}");

    $response->assertSee('value="'.$fulfillment->id.'" selected', escape: false);
});

it('shows a seller\'s listings, fulfillments, payouts and folded balance', function (): void {
    $seller = $this->seller('Blue Kiln Studio');
    $this->listing($seller, ['title' => 'Nine Herons', 'status' => ListingStatus::ForSale]);
    $fulfillment = $this->deliveredFulfillmentFor($seller, priceCents: 10000);
    app(RunWeeklyPayout::class)($this->moment('2026-08-24 09:00:00'));

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/sellers/{$seller->id}");

    $response->assertOk();
    $response->assertSee('Nine Herons');
    $response->assertSee($fulfillment->id);
    $response->assertSee('Escrow balance');
    $response->assertSee('Payouts');
    $response->assertSee('$90.00');
});

it('says so on a seller with no listings, fulfillments or payouts at all', function (): void {
    $seller = $this->seller('Quiet Press');

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/sellers/{$seller->id}");

    $response->assertOk();
    $response->assertSee('No listings.');
    $response->assertSee('No fulfillments.');
    $response->assertSee('No payouts yet.');
});

it('shows the list panes empty-detail prompt on the index route', function (): void {
    $this->seller('Blue Kiln Studio');

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/sellers');

    $response->assertOk();
    $response->assertSee('Choose a seller to see their shop.');
});

it('renders the list pane beside the detail pane, with a sibling seller still on the list', function (): void {
    $this->seller('Rye Press');
    $viewed = $this->seller('Blue Kiln Studio');

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/sellers/{$viewed->id}");

    $response->assertOk();
    $response->assertSee('Blue Kiln Studio');
    $response->assertSee('Rye Press');
});

it('caps the list pane at the window size, however many sellers exist', function (): void {
    Seller::factory()->count(ListPaneWindow::SIZE + 5)->create();

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/sellers');

    $response->assertOk();
    expect(substr_count((string) $response->getContent(), 'data-pane-cell="'))->toBe(ListPaneWindow::SIZE);
});

it('keeps the viewed seller on the list pane even when they sort outside the window', function (): void {
    $viewed = $this->seller('Blue Kiln Studio');
    Seller::where('id', $viewed->id)->update(['created_at' => now()->subDay()]);
    Seller::factory()->count(ListPaneWindow::SIZE + 5)->create();

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/sellers/{$viewed->id}");

    $response->assertOk();
    $response->assertSee('Blue Kiln Studio');
    expect(substr_count((string) $response->getContent(), 'data-pane-cell="'))->toBe(ListPaneWindow::SIZE + 1);
});

it('says how many sellers the list pane is not showing, linked to the full list', function (): void {
    Seller::factory()->count(ListPaneWindow::SIZE + 5)->create();

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/sellers');

    $response->assertOk();
    $response->assertSee('Showing 50 of', false);
    $response->assertSee('href="'.route('admin.sellers.index').'"', escape: false);
});

it('says nothing about a window that already holds every seller', function (): void {
    $this->seller('Blue Kiln Studio');

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/sellers');

    $response->assertOk();
    $response->assertDontSee('Showing');
});

it('answers not found for a value that is not a seller id, the same as an unknown one', function (string $id): void {
    $this->actingAs($this->admin(), 'admin')->get("/admin/sellers/{$id}")->assertNotFound();
})->with([
    'another table prefix' => 'cus_01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'a bare ULID' => '01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'a value of no shape at all' => 'nonsense',
    'a seller that does not exist' => 'sel_01J5X3M9A2K8YB7Q4R6T1V0WZE',
]);

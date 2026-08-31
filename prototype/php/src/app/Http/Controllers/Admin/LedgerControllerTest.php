<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\LedgerEntry;
use App\Support\ListPaneWindow;

it('renders no list pane — a full-content section, not list+detail', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/ledger');

    $response->assertOk();
    $response->assertDontSee('xl:w-[400px]', escape: false);
});

it('lists every ledger entry with no filter applied, folding the total of what it shows', function (): void {
    $this->deliveredFulfillmentFor($this->seller('Blue Kiln Studio'), priceCents: 10000);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/ledger');

    $response->assertOk();
    $response->assertSee('Blue Kiln Studio');
    expect($response->getContent())->toMatch('/data-cell="type"[^<]*>Held/');
});

it('filters by seller, folding the total to that seller\'s entries alone', function (): void {
    $this->deliveredFulfillmentFor($this->seller('Blue Kiln Studio'), priceCents: 10000);
    $second = $this->seller('Rye Press');
    $this->deliveredFulfillmentFor($second, priceCents: 5000);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/ledger?seller='.$second->id);

    $response->assertOk();
    $content = (string) $response->getContent();
    expect($content)->toMatch('/data-cell="seller"[\s\S]*?Rye Press/');
    expect($content)->not->toMatch('/data-cell="seller"[\s\S]*?Blue Kiln Studio/');
});

it('folds the totals tiles to the filtered set rather than the platform', function (): void {
    // Blue Kiln: one sale delivered, so $90.00 released and nothing held.
    // Rye Press: one sale delivered ($45.00 released) and one still awaiting
    // shipment ($180.00 held).
    $this->deliveredFulfillmentFor($this->seller('Blue Kiln Studio'), priceCents: 10000);
    $rye = $this->seller('Rye Press');
    $this->deliveredFulfillmentFor($rye, priceCents: 5000);
    $this->paidFulfillmentFor($rye, priceCents: 20000);
    $admin = $this->admin();

    $platform = (string) $this->actingAs($admin, 'admin')->get('/admin/ledger')->getContent();
    $bySeller = (string) $this->actingAs($admin, 'admin')->get('/admin/ledger?seller='.$rye->id)->getContent();
    $byType = (string) $this->actingAs($admin, 'admin')->get('/admin/ledger?type=held')->getContent();

    expect($platform)->toContain('data-stat="held">$180.00')
        ->and($platform)->toContain('data-stat="available">$135.00');

    expect($bySeller)->toContain('data-stat="held">$180.00')
        ->and($bySeller)->toContain('data-stat="available">$45.00');

    // Every hold the ledger holds, none of the releases that emptied two of
    // them: $90.00 + $45.00 + $180.00.
    expect($byType)->toContain('data-stat="held">$315.00')
        ->and($byType)->toContain('data-stat="available">$0.00');
});

it('filters by type, leaving every other type out', function (): void {
    $this->deliveredFulfillmentFor($this->seller(), priceCents: 10000);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/ledger?type=released');

    $response->assertOk();
    $content = (string) $response->getContent();
    expect($content)->toMatch('/data-cell="type"[^<]*>Released/');
    expect($content)->not->toMatch('/data-cell="type"[^<]*>Held/');
});

it('reads an empty seller or type filter as no filter at all', function (): void {
    $this->deliveredFulfillmentFor($this->seller('Blue Kiln Studio'), priceCents: 10000);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/ledger?seller=&type=');

    $response->assertOk();
    $response->assertSee('Blue Kiln Studio');
});

it('offers every LedgerEntryType value on the type filter', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/ledger');

    $response->assertOk();
    $response->assertSee('<option value="held"', escape: false);
    $response->assertSee('<option value="released"', escape: false);
    $response->assertSee('<option value="paid_out"', escape: false);
    $response->assertSee('<option value="refunded"', escape: false);
});

it('says so rather than showing an empty table when nothing matches the filter', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/ledger');

    $response->assertOk();
    $response->assertSee('No ledger entries match this filter.');
});

it('sends a guest to the admin login page', function (): void {
    $this->get('/admin/ledger')->assertRedirect(route('auth.admin.login'));
});

it('caps the rendered entries at the window size, however many exist', function (): void {
    LedgerEntry::factory()->count(ListPaneWindow::SIZE + 5)->create();

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/ledger');

    $response->assertOk();
    preg_match_all('/data-entry="([^"]+)"/', (string) $response->getContent(), $matches);
    expect(array_unique($matches[1]))->toHaveCount(ListPaneWindow::SIZE);
});

it('says how many ledger entries the window is not showing, linked to the full list', function (): void {
    LedgerEntry::factory()->count(ListPaneWindow::SIZE + 5)->create();

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/ledger');

    $response->assertOk();
    $response->assertSee('Showing 50 of', false);
    $response->assertSee('href="'.route('admin.ledger').'"', escape: false);
});

it('says nothing about a window that already holds every entry', function (): void {
    $this->deliveredFulfillmentFor($this->seller(), priceCents: 10000);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/ledger');

    $response->assertOk();
    $response->assertDontSee('Showing');
});

it('folds the totals over every matching entry, not just the rendered window', function (): void {
    $seller = $this->seller('Blue Kiln Studio');
    LedgerEntry::factory()->count(ListPaneWindow::SIZE + 5)->held()->create(['seller_id' => $seller->id, 'amount_cents' => 100]);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/ledger');

    $response->assertOk();
    // Each entry sits on its own fulfillment, so nothing offsets it — the
    // fold must add all 55, not just the 50 the window renders.
    $response->assertSee('data-stat="held">$55.00', false);
});

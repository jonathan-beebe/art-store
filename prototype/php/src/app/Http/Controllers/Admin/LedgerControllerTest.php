<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

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

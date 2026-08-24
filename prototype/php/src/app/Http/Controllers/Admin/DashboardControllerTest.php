<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Fulfillment\RefundFulfillment;
use App\Domain\Listings\ListingStatus;
use App\Models\Admin;
use App\Models\PageViewCount;

it('renders the admin dashboard', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin');

    $response->assertOk();
    $response->assertSee('Dashboard');
});

it('links to every page of the directory', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin');

    $response->assertSee('href="'.route('admin.sellers.index').'"', escape: false);
    $response->assertSee('href="'.route('admin.customers.index').'"', escape: false);
    $response->assertSee('href="'.route('admin.listings.index').'"', escape: false);
    $response->assertSee('href="'.route('admin.orders.index').'"', escape: false);
    $response->assertSee('href="'.route('admin.fulfillments.index').'"', escape: false);
});

it('sends a guest to the admin login page', function (): void {
    $response = $this->get('/admin');

    $response->assertRedirect(route('auth.admin.login'));
});

it('sends a signed in seller to the admin login wall, not the dashboard', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/admin');

    $response->assertRedirect(route('auth.admin.login'));
});

it('sends a signed in customer to the admin login wall, not the dashboard', function (): void {
    $response = $this->actingAs($this->verifiedCustomer(), 'customer')->get('/admin');

    $response->assertRedirect(route('auth.admin.login'));
});

it('tallies every listing status, a status with no rows included', function (): void {
    $this->listing($this->seller(), ['status' => ListingStatus::ForSale]);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin');

    $response->assertOk();
    expect($response->getContent())
        ->toMatch('/data-status="for_sale"[\s\S]*?>1</')
        ->toMatch('/data-status="archived"[\s\S]*?>0</');
});

it('tallies every order and fulfillment status, a status with no rows included', function (): void {
    $this->paidFulfillmentFor($this->seller());

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin');

    $response->assertOk();
    expect($response->getContent())
        ->toMatch('/data-tally="orders"[\s\S]*?data-status="awaiting_payment"[\s\S]*?>1</')
        ->toMatch('/data-tally="orders"[\s\S]*?data-status="cancelled"[\s\S]*?>0</')
        ->toMatch('/data-tally="fulfillments"[\s\S]*?data-status="awaiting_shipment"[\s\S]*?>1</')
        ->toMatch('/data-tally="fulfillments"[\s\S]*?data-status="declined"[\s\S]*?>0</');
});

it('shows platform money: held, available, paid out, fees earned, fees refunded, and refunded', function (): void {
    $admin = Admin::factory()->create();
    $this->deliveredFulfillmentFor($this->seller(), priceCents: 10000);
    $refunded = $this->deliveredFulfillmentFor($this->seller(), priceCents: 5000);
    app(RefundFulfillment::class)($refunded, $admin, 'Arrived damaged.', $this->moment('2026-08-23 09:00:00'));

    $response = $this->actingAs($admin, 'admin')->get('/admin');

    $response->assertOk();
    expect($response->getContent())
        ->toMatch('/data-stat="held"[\s\S]*?\$0\.00/')
        ->toMatch('/data-stat="available"[\s\S]*?\$90\.00/')
        ->toMatch('/data-stat="fees-earned"[\s\S]*?\$10\.00/')
        ->toMatch('/data-stat="fees-refunded"[\s\S]*?\$5\.00/')
        ->toMatch('/data-stat="refunded"[\s\S]*?\$45\.00/');
});

it('shows page views for the seven days ending today', function (): void {
    PageViewCount::factory()->create(['day' => now()->format('Y-m-d'), 'count' => 4]);
    PageViewCount::factory()->create(['day' => now()->subDays(10)->format('Y-m-d'), 'count' => 100]);

    $response = $this->actingAs($this->admin(), 'admin')->get('/admin');

    $response->assertOk();
    expect($response->getContent())->toMatch('/data-stat="page-views-week"[\s\S]*?>4</');
});

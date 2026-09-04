<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Analytics;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Models\Funnel;

it('renders 200 with the funnel\'s own name and steps', function (): void {
    $funnel = Funnel::factory()->create(['name' => 'Gift Shopping', 'steps' => ['listing.view', 'listing.cart_add']]);

    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.funnels.show', $funnel));

    $response->assertOk();
    $response->assertSee('Gift Shopping');
    $response->assertSee('Listing views');
    $response->assertSee('Cart adds');
});

it('counts the funnel\'s own sessions for the range', function (): void {
    $listing = $this->listing($this->seller());
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingView, $listing->id, $customer->id, $this->moment('2026-08-19 09:00:00'), 'a'));
    $analytics->flush();

    $funnel = Funnel::factory()->create(['name' => 'Gift Shopping', 'steps' => ['listing.view', 'listing.cart_add']]);

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.funnels.show', ['funnel' => $funnel, 'range' => 7]));

    $response->assertOk();
    $response->assertSeeInOrder(['Visitors', '1', 'Listing views', '1']);
});

it('links to the funnel\'s own edit page', function (): void {
    $funnel = Funnel::factory()->create();

    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.funnels.show', $funnel));

    $response->assertOk();
    $response->assertSee('href="'.route('admin.funnels.edit', $funnel).'"', escape: false);
});

it('breadcrumbs back to the analytics index carrying the range', function (): void {
    $funnel = Funnel::factory()->create();

    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.funnels.show', ['funnel' => $funnel, 'range' => 7]));

    $response->assertOk();
    $response->assertSee('href="'.route('admin.analytics.index', ['range' => 7]).'"', escape: false);
});

it('answers 400 for an unrecognised range', function (): void {
    $funnel = Funnel::factory()->create();

    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.funnels.show', ['funnel' => $funnel, 'range' => 14]));

    $response->assertStatus(400);
});

it('answers not found for an unknown funnel id', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/analytics/funnels/fnl_01J5X3M9A2K8YB7Q4R6T1V0WZE');

    $response->assertNotFound();
});

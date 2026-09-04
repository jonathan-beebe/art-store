<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Analytics;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;

it('renders 200 with the store\'s facts, tiles, and feed', function (): void {
    $seller = $this->seller('Weasley Studio');
    $store = $this->storeFor($seller);
    $store->update(['name' => 'Weasleys\' Wizard Wheezes']);
    $customer = $this->verifiedCustomer();
    $customer->update(['email' => 'hermione@example.com']);
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forStore(AnalyticsEventName::StoreView, $store->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.stores.show', $store));

    $response->assertOk();
    $response->assertSee('Weasleys\' Wizard Wheezes');
    $response->assertSee($store->id);
    $response->assertSee($store->slug);
    $response->assertSee('Weasley Studio');
    $response->assertSee('hermione@example.com');
    $response->assertSee('Open seller');
    $response->assertSee('href="'.route('admin.sellers.show', $seller).'"', escape: false);
});

it('shows the store\'s visibility in its identity card', function (): void {
    $seller = $this->seller();
    $store = $this->storeFor($seller);

    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.stores.show', $store));

    $response->assertOk();
    $response->assertSee('Hidden');
});

it('filters the store feed by event name', function (): void {
    $seller = $this->seller();
    $store = $this->storeFor($seller);
    $customer = $this->verifiedCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forStore(AnalyticsEventName::StoreView, $store->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->recordEvent(AnalyticsEvent::forStore(AnalyticsEventName::StoreView, $store->id, $customer->id, $this->moment('2026-08-19 10:00:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.stores.show', ['store' => $store->id, 'event' => 'store.view']));

    $response->assertOk();
    $response->assertSee('2 of 2 shown, newest first');
});

it('answers 400 for an unrecognised event filter on the store page', function (): void {
    $store = $this->storeFor($this->seller());

    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.stores.show', ['store' => $store->id, 'event' => 'nonsense']));

    $response->assertStatus(400);
});

it('answers 400 for an unrecognised range on the store page', function (): void {
    $store = $this->storeFor($this->seller());

    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.stores.show', ['store' => $store->id, 'range' => '14']));

    $response->assertStatus(400);
});

it('answers not found for a value that is not a store id, the same as an unknown one', function (string $id): void {
    $this->actingAs($this->admin(), 'admin')->get("/admin/analytics/stores/{$id}")->assertNotFound();
})->with([
    'another table prefix' => 'lst_01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'a bare ULID' => '01J5X3M9A2K8YB7Q4R6T1V0WZE',
    'a value of no shape at all' => 'nonsense',
    'a store that does not exist' => 'sto_01J5X3M9A2K8YB7Q4R6T1V0WZE',
]);

it('names an anonymous actor "Anonymous visitor" on the store page\'s feed', function (): void {
    $store = $this->storeFor($this->seller());
    $customer = $this->anonymousCustomer();
    $analytics = app(Analytics::class);

    $analytics->recordEvent(AnalyticsEvent::forStore(AnalyticsEventName::StoreView, $store->id, $customer->id, $this->moment('2026-08-19 09:00:00')));
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')->get(route('admin.analytics.stores.show', $store));

    $response->assertOk();
    $response->assertSee('Anonymous visitor');
});

it('renders the store page on a fixed number of queries however many actors its feed holds', function (): void {
    $store = $this->storeFor($this->seller());
    $analytics = app(Analytics::class);

    foreach (range(1, 15) as $i) {
        $customer = $this->verifiedCustomer();
        $analytics->recordEvent(AnalyticsEvent::forStore(AnalyticsEventName::StoreView, $store->id, $customer->id, $this->moment('2026-08-19 09:00:00')->modify("+{$i} minutes"), "e{$i}"));
    }
    $analytics->flush();

    $this->travelTo($this->moment('2026-08-24 12:00:00'));
    $response = $this->actingAs($this->admin(), 'admin')
        ->expectsDatabaseQueryCount(5, 'sqlite')
        ->expectsDatabaseQueryCount(6, 'analytics')
        ->get(route('admin.analytics.stores.show', $store));

    $response->assertOk();
});

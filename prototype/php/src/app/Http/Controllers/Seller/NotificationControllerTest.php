<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Money\Money;
use App\Models\Seller;
use App\Notifications\ItemSold;
use Illuminate\Notifications\DatabaseNotification;

$notify = function (Seller $seller, string $orderId): DatabaseNotification {
    $seller->notify(new ItemSold($orderId, Money::fromCents(9000)));

    return $seller->notifications()->firstOrFail();
};

it('lists the sellers notifications', function () use ($notify): void {
    $seller = $this->seller();
    $notify($seller, 'ord_00000000000000000000000041');

    $response = $this->actingAs($seller, 'seller')->get('/seller/notifications');

    $response->assertOk();
    $response->assertSee('Item sold');
    $response->assertSee('Order ord_00000000000000000000000041 is paid. $90.00 is held until the customer confirms delivery.');
    $response->assertSee('Unread');
});

it('keeps another sellers notifications off the page', function () use ($notify): void {
    $notify($this->seller('Other Studio'), 'ord_00000000000000000000000077');

    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/notifications');

    $response->assertDontSee('Order ord_00000000000000000000000077');
});

it('marks a notification read', function () use ($notify): void {
    $seller = $this->seller();
    $notification = $notify($seller, 'ord_00000000000000000000000041');

    $response = $this->actingAs($seller, 'seller')->post("/seller/notifications/{$notification->id}/read");

    $response->assertRedirect(route('seller.notifications.index'));
    expect($notification->refresh()->read_at)->not->toBeNull();
});

it('stops offering to mark a read notification read', function () use ($notify): void {
    $seller = $this->seller();
    $notification = $notify($seller, 'ord_00000000000000000000000041');
    $this->actingAs($seller, 'seller')->post("/seller/notifications/{$notification->id}/read");

    $response = $this->actingAs($seller, 'seller')->get('/seller/notifications');

    $response->assertDontSee('Mark as read');
});

it('refuses to mark another sellers notification read', function () use ($notify): void {
    $notification = $notify($this->seller('Other Studio'), 'ord_00000000000000000000000077');

    $response = $this->actingAs($this->seller(), 'seller')->post("/seller/notifications/{$notification->id}/read");

    $response->assertNotFound();
    expect($notification->refresh()->read_at)->toBeNull();
});

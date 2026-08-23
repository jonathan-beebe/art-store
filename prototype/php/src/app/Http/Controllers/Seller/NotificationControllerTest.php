<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Models\Notification;
use App\Models\Seller;

$notify = function (Seller $seller, string $subject): Notification {
    return Notification::create([
        'seller_id' => $seller->id,
        'subject' => $subject,
        'body' => 'A print sold for $90.00.',
    ]);
};

it('lists the sellers notifications', function () use ($notify): void {
    $seller = $this->seller();
    $notify($seller, 'Item sold');

    $response = $this->actingAs($seller, 'seller')->get('/seller/notifications');

    $response->assertOk();
    $response->assertSee('Item sold');
    $response->assertSee('Unread');
});

it('keeps another sellers notifications off the page', function () use ($notify): void {
    $notify($this->seller('Other Studio'), 'Not Mine');

    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/notifications');

    $response->assertDontSee('Not Mine');
});

it('marks a notification read', function () use ($notify): void {
    $seller = $this->seller();
    $notification = $notify($seller, 'Item sold');

    $response = $this->actingAs($seller, 'seller')->post("/seller/notifications/{$notification->id}/read");

    $response->assertRedirect(route('seller.notifications.index'));
    expect($notification->fresh()->read_at)->not->toBeNull();
});

it('stops offering to mark a read notification read', function () use ($notify): void {
    $seller = $this->seller();
    $notification = $notify($seller, 'Item sold');
    $this->actingAs($seller, 'seller')->post("/seller/notifications/{$notification->id}/read");

    $response = $this->actingAs($seller, 'seller')->get('/seller/notifications');

    $response->assertDontSee('Mark as read');
});

it('refuses to mark another sellers notification read', function () use ($notify): void {
    $notification = $notify($this->seller('Other Studio'), 'Not Mine');

    $response = $this->actingAs($this->seller(), 'seller')->post("/seller/notifications/{$notification->id}/read");

    $response->assertNotFound();
    expect($notification->fresh()->read_at)->toBeNull();
});

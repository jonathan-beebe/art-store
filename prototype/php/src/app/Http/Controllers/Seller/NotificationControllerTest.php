<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Models\Notification;
use App\Models\Seller;
use Tests\CommerceTestCase;

final class NotificationControllerTest extends CommerceTestCase
{
    public function test_it_sends_a_signed_out_visitor_to_the_sign_in_page(): void
    {
        $this->get('/seller/notifications')->assertRedirect(route('auth.seller.login'));
    }

    public function test_it_lists_the_sellers_notifications(): void
    {
        $seller = $this->seller();
        $this->notify($seller, 'Item sold');

        $response = $this->actingAs($seller, 'seller')->get('/seller/notifications');

        $response->assertOk();
        $response->assertSee('Item sold');
        $response->assertSee('Unread');
    }

    public function test_it_keeps_another_sellers_notifications_off_the_page(): void
    {
        $this->notify($this->seller('Other Studio'), 'Not Mine');

        $response = $this->actingAs($this->seller(), 'seller')->get('/seller/notifications');

        $response->assertDontSee('Not Mine');
    }

    public function test_it_marks_a_notification_read(): void
    {
        $seller = $this->seller();
        $notification = $this->notify($seller, 'Item sold');

        $response = $this->actingAs($seller, 'seller')->post("/seller/notifications/{$notification->id}/read");

        $response->assertRedirect(route('seller.notifications.index'));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_it_stops_offering_to_mark_a_read_notification_read(): void
    {
        $seller = $this->seller();
        $notification = $this->notify($seller, 'Item sold');
        $this->actingAs($seller, 'seller')->post("/seller/notifications/{$notification->id}/read");

        $response = $this->actingAs($seller, 'seller')->get('/seller/notifications');

        $response->assertDontSee('Mark as read');
    }

    public function test_it_refuses_to_mark_another_sellers_notification_read(): void
    {
        $notification = $this->notify($this->seller('Other Studio'), 'Not Mine');

        $response = $this->actingAs($this->seller(), 'seller')->post("/seller/notifications/{$notification->id}/read");

        $response->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    private function notify(Seller $seller, string $subject): Notification
    {
        return Notification::create([
            'seller_id' => $seller->id,
            'subject' => $subject,
            'body' => 'A print sold for $90.00.',
        ]);
    }
}

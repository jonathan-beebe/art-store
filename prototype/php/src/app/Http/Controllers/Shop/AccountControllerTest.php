<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Notifications\Notify;
use App\Domain\Notifications\NotificationMessage;
use App\Domain\Notifications\RecipientType;
use App\Models\Customer;
use App\Models\Notification;
use Tests\StorefrontTestCase;

final class AccountControllerTest extends StorefrontTestCase
{
    public function test_it_shows_the_verified_address(): void
    {
        $this->actingAs(Customer::factory()->create(['email' => 'shopper@example.com']), 'customer');

        $response = $this->get('/account');

        $response->assertOk();
        $response->assertSee('shopper@example.com');
    }

    public function test_it_offers_a_sign_out_form(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer');

        $response = $this->get('/account');

        $response->assertSee('action="'.route('auth.customer.logout').'"', escape: false);
    }

    public function test_it_sends_a_signed_out_visitor_to_the_login_page(): void
    {
        $response = $this->get('/account');

        $response->assertRedirect(route('auth.customer.login'));
    }

    public function test_it_lists_the_notifications_of_the_customer(): void
    {
        $shopper = Customer::factory()->create();
        $this->actingAs($shopper, 'customer');
        $this->notify($shopper, 41);
        $this->notify(Customer::factory()->create(), 77);

        $response = $this->get('/account');

        $response->assertSee('Order shipped');
        $response->assertSee('Order #41 shipped with Royal Mail.');
        $response->assertDontSee('Order #77');
    }

    public function test_it_marks_a_notification_read(): void
    {
        $shopper = Customer::factory()->create();
        $this->actingAs($shopper, 'customer');
        $notification = $this->notify($shopper, 41);

        $response = $this->post(route('shop.account.notifications.read', $notification));

        $response->assertRedirect(route('shop.account'));
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_it_leaves_another_customer_notification_alone(): void
    {
        $this->actingAs(Customer::factory()->create(), 'customer');
        $notification = $this->notify(Customer::factory()->create(), 41);

        $response = $this->post(route('shop.account.notifications.read', $notification));

        $response->assertNotFound();
        $this->assertNull($notification->fresh()->read_at);
    }

    private function notify(Customer $customer, int $orderId): Notification
    {
        return app(Notify::class)(
            RecipientType::Customer,
            $customer->id,
            NotificationMessage::orderShipped($orderId, 'Royal Mail', 'RM123456789GB'),
        );
    }
}

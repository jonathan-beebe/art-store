<?php

namespace App\Http\Controllers\Auth;

use App\Models\Customer;
use App\Models\MagicLink;
use App\Models\Seller;
use App\Support\CustomerIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MagicLinkVerificationControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_first_time_seller_gets_an_account_and_lands_on_the_dashboard(): void
    {
        $response = $this->get($this->sellerLinkFor('artist@example.com'));

        $response->assertRedirect(route('seller.dashboard'));
        $this->assertAuthenticated('seller');
        $seller = Seller::sole();
        $this->assertSame('artist@example.com', $seller->email);
        $this->assertNotNull($seller->email_verified_at);
    }

    public function test_a_returning_seller_signs_in_to_the_same_account(): void
    {
        $seller = Seller::factory()->create(['email' => 'artist@example.com']);

        $this->get($this->sellerLinkFor('Artist@Example.com'));

        $this->assertSame(1, Seller::count());
        $this->assertAuthenticatedAs($seller->fresh(), 'seller');
    }

    public function test_an_expired_link_is_refused(): void
    {
        config(['magic_links.expiry_minutes' => 15]);
        $url = $this->sellerLinkFor('artist@example.com');

        $this->travel(16)->minutes();
        $response = $this->get($url);

        $response->assertRedirect(route('auth.seller.login'));
        $response->assertSessionHas('error', 'That sign-in link has expired. Ask for a new one.');
        $this->assertGuest('seller');
        $this->assertSame(0, Seller::count());
    }

    public function test_a_link_only_works_once(): void
    {
        $url = $this->sellerLinkFor('artist@example.com');
        $this->get($url);
        $this->post('/seller/logout');

        $response = $this->get($url);

        $response->assertRedirect(route('auth.seller.login'));
        $response->assertSessionHas('error', 'That sign-in link has already been used. Ask for a new one.');
        $this->assertGuest('seller');
    }

    public function test_an_unknown_token_is_refused(): void
    {
        $response = $this->get('/auth/magic/'.str_repeat('a', 80));

        $response->assertRedirect(route('auth.customer.login'));
        $response->assertSessionHas('error', 'That sign-in link is not valid. Ask for a new one.');
    }

    public function test_verification_marks_the_link_consumed(): void
    {
        $this->get($this->sellerLinkFor('artist@example.com'));

        $this->assertNotNull(MagicLink::sole()->consumed_at);
    }

    public function test_an_anonymous_customer_claims_their_own_row(): void
    {
        $anonymous = Customer::factory()->anonymous()->create();

        $response = $this->withCookie(CustomerIdentity::COOKIE, (string) $anonymous->id)
            ->get($this->customerLinkFor('shopper@example.com'));

        $response->assertRedirect(route('shop.account'));
        $response->assertCookie(CustomerIdentity::COOKIE, (string) $anonymous->id);
        $this->assertAuthenticatedAs($anonymous->fresh(), 'customer');
        $this->assertSame(1, Customer::count());
        $this->assertSame('shopper@example.com', $anonymous->fresh()->email);
        $this->assertDatabaseCount('customer_merges', 0);
    }

    public function test_an_anonymous_customer_merges_into_the_account_that_owns_the_address(): void
    {
        $verified = Customer::factory()->create(['email' => 'shopper@example.com']);
        $anonymous = Customer::factory()->anonymous()->create();

        $response = $this->withCookie(CustomerIdentity::COOKIE, (string) $anonymous->id)
            ->get($this->customerLinkFor('shopper@example.com'));

        $response->assertRedirect(route('shop.account'));
        $response->assertCookie(CustomerIdentity::COOKIE, (string) $verified->id);
        $this->assertAuthenticatedAs($verified->fresh(), 'customer');
        $this->assertDatabaseHas('customer_merges', [
            'anonymous_customer_id' => $anonymous->id,
            'customer_id' => $verified->id,
        ]);
    }

    public function test_a_customer_with_no_cookie_gets_a_fresh_verified_account(): void
    {
        $response = $this->get($this->customerLinkFor('shopper@example.com'));

        $customer = Customer::sole();
        $response->assertCookie(CustomerIdentity::COOKIE, (string) $customer->id);
        $this->assertAuthenticatedAs($customer, 'customer');
        $this->assertNotNull($customer->email_verified_at);
    }

    public function test_verifying_an_address_a_guest_order_left_unverified_marks_it_verified(): void
    {
        $customer = Customer::factory()->create([
            'email' => 'shopper@example.com',
            'email_verified_at' => null,
        ]);

        $this->get($this->customerLinkFor('shopper@example.com'));

        $this->assertNotNull($customer->fresh()->email_verified_at);
    }

    public function test_it_honours_a_local_destination_on_the_link(): void
    {
        $response = $this->get($this->customerLinkFor('shopper@example.com', '/checkout'));

        $response->assertRedirect('/checkout');
    }

    public function test_it_ignores_a_destination_on_another_host(): void
    {
        $this->post('/login', ['email' => 'shopper@example.com']);
        MagicLink::sole()->forceFill(['redirect_to' => 'http://evil.example/steal'])->save();

        $response = $this->get(session('debug_magic_link'));

        $response->assertRedirect(route('shop.account'));
    }

    private function sellerLinkFor(string $email): string
    {
        $this->post('/seller/login', ['email' => $email]);

        return session('debug_magic_link');
    }

    private function customerLinkFor(string $email, ?string $redirectTo = null): string
    {
        $this->post('/login', array_filter(['email' => $email, 'redirect_to' => $redirectTo]));

        return session('debug_magic_link');
    }
}

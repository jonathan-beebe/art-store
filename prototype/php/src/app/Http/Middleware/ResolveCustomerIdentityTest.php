<?php

namespace App\Http\Middleware;

use App\Models\Customer;
use App\Models\CustomerMerge;
use App\Support\CustomerIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class ResolveCustomerIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_first_visit_gets_an_anonymous_customer_and_a_cookie(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $customer = Customer::sole();
        $this->assertTrue($customer->isAnonymous());
        $response->assertCookie(CustomerIdentity::COOKIE, (string) $customer->id);
    }

    public function test_a_second_visit_reuses_the_same_customer(): void
    {
        $this->get('/');
        $customer = Customer::sole();

        $this->withCookie(CustomerIdentity::COOKIE, (string) $customer->id)->get('/');

        $this->assertSame(1, Customer::count());
    }

    public function test_a_cookie_pointing_at_a_deleted_customer_starts_over(): void
    {
        $response = $this->withCookie(CustomerIdentity::COOKIE, '9999')->get('/');

        $customer = Customer::sole();
        $response->assertCookie(CustomerIdentity::COOKIE, (string) $customer->id);
    }

    public function test_a_cookie_holding_junk_starts_over(): void
    {
        $response = $this->withCookie(CustomerIdentity::COOKIE, 'not-an-id')->get('/');

        $this->assertSame(1, Customer::count());
        $response->assertCookie(CustomerIdentity::COOKIE, (string) Customer::sole()->id);
    }

    public function test_a_stale_cookie_resolves_through_a_recorded_merge(): void
    {
        $anonymous = Customer::factory()->anonymous()->create();
        $verified = Customer::factory()->create();
        CustomerMerge::create([
            'anonymous_customer_id' => $anonymous->id,
            'customer_id' => $verified->id,
        ]);

        $response = $this->withCookie(CustomerIdentity::COOKIE, (string) $anonymous->id)->get('/');

        $response->assertCookie(CustomerIdentity::COOKIE, (string) $verified->id);
        $this->assertSame(2, Customer::count());
    }

    public function test_a_signed_in_customer_outranks_the_cookie(): void
    {
        $anonymous = Customer::factory()->anonymous()->create();
        $signedIn = Customer::factory()->create();

        $response = $this->actingAs($signedIn, 'customer')
            ->withCookie(CustomerIdentity::COOKIE, (string) $anonymous->id)
            ->get('/');

        $response->assertCookie(CustomerIdentity::COOKIE, (string) $signedIn->id);
    }

    public function test_it_exposes_the_resolved_customer_to_the_customer_helper(): void
    {
        Route::middleware(['web', 'customer.identity'])
            ->get('/customer-helper-probe', fn () => 'customer:'.customer()?->id);

        $response = $this->get('/customer-helper-probe');

        $response->assertSee('customer:'.Customer::sole()->id);
    }
}

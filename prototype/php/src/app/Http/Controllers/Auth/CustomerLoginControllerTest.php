<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\ActorType;
use App\Models\Customer;
use App\Models\MagicLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerLoginControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_an_email_form(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('name="email"', escape: false);
        $response->assertSee('action="'.route('auth.customer.send').'"', escape: false);
    }

    public function test_it_issues_a_customer_link_for_the_submitted_address(): void
    {
        $this->post('/login', ['email' => 'shopper@example.com']);

        $link = MagicLink::sole();
        $this->assertSame('shopper@example.com', $link->email);
        $this->assertSame(ActorType::Customer, $link->actor_type);
    }

    public function test_it_carries_a_local_destination_onto_the_link(): void
    {
        $this->post('/login', ['email' => 'shopper@example.com', 'redirect_to' => '/checkout']);

        $this->assertSame('/checkout', MagicLink::sole()->redirect_to);
    }

    public function test_it_tells_the_visitor_to_check_their_email(): void
    {
        $response = $this->followingRedirects()->post('/login', ['email' => 'shopper@example.com']);

        $response->assertSee('Check your email');
        $response->assertSee('shopper@example.com');
    }

    public function test_it_rejects_a_submission_without_a_usable_address(): void
    {
        $response = $this->post('/login', ['email' => '']);

        $response->assertSessionHasErrors('email');
        $this->assertSame(0, MagicLink::count());
    }

    public function test_it_sends_a_signed_in_customer_to_their_account(): void
    {
        $response = $this->actingAs(Customer::factory()->create(), 'customer')->get('/login');

        $response->assertRedirect(route('shop.account'));
    }
}

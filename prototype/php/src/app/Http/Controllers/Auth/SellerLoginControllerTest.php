<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\ActorType;
use App\Models\MagicLink;
use App\Models\Seller;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SellerLoginControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_an_email_form(): void
    {
        $response = $this->get('/seller/login');

        $response->assertOk();
        $response->assertSee('name="email"', escape: false);
        $response->assertSee('action="'.route('auth.seller.send').'"', escape: false);
    }

    public function test_it_issues_a_seller_link_for_the_submitted_address(): void
    {
        $this->post('/seller/login', ['email' => 'artist@example.com']);

        $link = MagicLink::sole();
        $this->assertSame('artist@example.com', $link->email);
        $this->assertSame(ActorType::Seller, $link->actor_type);
    }

    public function test_it_tells_the_visitor_to_check_their_email(): void
    {
        $response = $this->followingRedirects()->post('/seller/login', ['email' => 'artist@example.com']);

        $response->assertSee('Check your email');
        $response->assertSee('artist@example.com');
    }

    public function test_it_flashes_the_link_for_the_debug_alert(): void
    {
        $this->post('/seller/login', ['email' => 'artist@example.com']);

        $this->assertStringStartsWith(url('/auth/magic/'), session('debug_magic_link'));
    }

    public function test_it_rejects_a_submission_without_a_usable_address(): void
    {
        $response = $this->post('/seller/login', ['email' => 'not-an-address']);

        $response->assertSessionHasErrors('email');
        $this->assertSame(0, MagicLink::count());
    }

    public function test_it_sends_a_signed_in_seller_to_the_dashboard(): void
    {
        $response = $this->actingAs(Seller::factory()->create(), 'seller')->get('/seller/login');

        $response->assertRedirect(route('seller.dashboard'));
    }
}

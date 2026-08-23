<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Domain\Auth\ActorType;
use App\Domain\Auth\MagicLinkToken;
use App\Models\MagicLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SendMagicLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_only_the_hash_of_the_token_it_delivers(): void
    {
        $url = $this->send('Artist@Example.com', ActorType::Seller);

        $token = basename(parse_url($url, PHP_URL_PATH));
        $link = MagicLink::sole();

        $this->assertSame(MagicLinkToken::hash($token), $link->token_hash);
        $this->assertStringNotContainsString($token, $link->token_hash);
    }

    public function test_it_normalizes_the_address_on_the_link(): void
    {
        $this->send('Artist@Example.com', ActorType::Seller);

        $this->assertSame('artist@example.com', MagicLink::sole()->email);
    }

    public function test_it_records_the_actor_the_link_signs_in(): void
    {
        $this->send('shopper@example.com', ActorType::Customer);

        $this->assertSame(ActorType::Customer, MagicLink::sole()->actor_type);
    }

    public function test_it_expires_the_link_after_the_configured_window(): void
    {
        config(['magic_links.expiry_minutes' => 15]);
        $this->freezeTime();

        $this->send('artist@example.com', ActorType::Seller);

        $this->assertSame(
            now()->addMinutes(15)->format('Y-m-d H:i:s'),
            MagicLink::sole()->expires_at->format('Y-m-d H:i:s'),
        );
    }

    public function test_it_carries_the_page_the_visitor_was_heading_for(): void
    {
        $this->send('shopper@example.com', ActorType::Customer, '/checkout');

        $this->assertSame('/checkout', MagicLink::sole()->redirect_to);
    }

    private function send(string $email, ActorType $actorType, ?string $redirectTo = null): string
    {
        $this->app->call(fn (SendMagicLink $send) => $send($email, $actorType, $redirectTo));

        return session('debug_magic_link');
    }
}

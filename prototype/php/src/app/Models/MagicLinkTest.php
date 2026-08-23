<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Auth\ActorType;
use App\Domain\Auth\MagicLinkStatus;
use App\Domain\Auth\MagicLinkToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MagicLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_finds_a_link_by_the_token_behind_its_hash(): void
    {
        $link = $this->link('open-sesame');

        $this->assertTrue($link->is(MagicLink::forToken('open-sesame')->first()));
    }

    public function test_it_finds_nothing_for_a_token_it_never_issued(): void
    {
        $this->link('open-sesame');

        $this->assertNull(MagicLink::forToken('guess')->first());
    }

    public function test_a_fresh_link_reports_itself_usable(): void
    {
        $link = $this->link('open-sesame');

        $this->assertSame(MagicLinkStatus::Usable, $link->statusAt(now()));
    }

    public function test_a_link_reports_itself_expired_past_its_window(): void
    {
        $link = $this->link('open-sesame');

        $this->assertSame(MagicLinkStatus::Expired, $link->statusAt(now()->addMinutes(16)));
    }

    public function test_consuming_a_link_stamps_it_and_closes_it(): void
    {
        $link = $this->link('open-sesame');

        $link->consume(now());

        $this->assertNotNull($link->fresh()->consumed_at);
        $this->assertSame(MagicLinkStatus::Consumed, $link->fresh()->statusAt(now()));
    }

    private function link(string $token): MagicLink
    {
        return MagicLink::create([
            'token_hash' => MagicLinkToken::hash($token),
            'email' => 'artist@example.com',
            'actor_type' => ActorType::Seller,
            'expires_at' => now()->addMinutes(15),
        ]);
    }
}

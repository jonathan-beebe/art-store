<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Actions\Messaging\MarkConversationRead;
use App\Domain\Messaging\ConversationSubject;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/**
 * The rail and drawer render the same nav-item markup, so every other
 * section's own count chip (Sellers, Customers, ...) can coincidentally
 * match a bare `>1</span>` too — this isolates one link's own `<a>...</a>`
 * block before checking it for a chip.
 */
function navLinkMarkup(string $html, string $href): string
{
    preg_match('#<a\s+href="'.preg_quote($href, '#').'"[^>]*>[\s\S]*?</a>#', $html, $matches);

    return $matches[0] ?? '';
}

it('counts the messages across every thread the admin has not read', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();
    $conversation = Conversation::factory()
        ->forSubject(ConversationSubject::adminSeller($admin->id, $seller->id))
        ->create();
    Message::factory()->from($seller)->unread()->create(['conversation_id' => $conversation->id]);
    Message::factory()->from($admin)->create(['conversation_id' => $conversation->id]);

    $response = $this->actingAs($admin, 'admin')->get('/admin');
    $html = (string) $response->getContent();

    expect(navLinkMarkup($html, route('admin.messages.index')))->toContain('>1</span>');
});

it('drops the count once the thread is marked read', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();
    $conversation = Conversation::factory()
        ->forSubject(ConversationSubject::adminSeller($admin->id, $seller->id))
        ->create();
    Message::factory()->from($seller)->unread()->create(['conversation_id' => $conversation->id]);
    app(MarkConversationRead::class)($conversation, $admin, $this->moment('2026-08-20 09:00:00'));

    $response = $this->actingAs($admin, 'admin')->get('/admin');
    $html = (string) $response->getContent();

    expect(navLinkMarkup($html, route('admin.messages.index')))->not->toContain('>1</span>');
});

it('carries the count onto every admin page without the controller passing it', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();
    $conversation = Conversation::factory()
        ->forSubject(ConversationSubject::adminSeller($admin->id, $seller->id))
        ->create();
    Message::factory()->from($seller)->unread()->create(['conversation_id' => $conversation->id]);

    $sellers = (string) $this->actingAs($admin, 'admin')->get('/admin/sellers')->getContent();
    $customers = (string) $this->actingAs($admin, 'admin')->get('/admin/customers')->getContent();

    expect(navLinkMarkup($sellers, route('admin.messages.index')))->toContain('>1</span>');
    expect(navLinkMarkup($customers, route('admin.messages.index')))->toContain('>1</span>');
});

it('renders a page with no admin signed in without the nav', function (): void {
    $response = $this->get('/admin/login');

    $response->assertOk();
    $response->assertDontSee('aria-label="Admin tools"', escape: false);
});

it('renders the lg-and-up nav rail with the active section marked (DSGN-006)', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/orders');

    $response->assertOk();
    $html = (string) $response->getContent();

    // Two nav landmarks carry the section links: the lg+ rail and the
    // below-lg drawer — they share one nav-items partial so they can't
    // drift.
    expect(substr_count($html, 'aria-label="Admin tools"'))->toBe(2);
    // The Orders link is marked current in both — proof the rail renders
    // the same active-section logic as the drawer.
    expect(preg_match_all('/<a\s+href="'.preg_quote(route('admin.orders.index'), '/').'"\s+aria-current="page"/', $html))->toBe(2);
});

it('reads the unread count and every nav badge in one query', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();
    $conversation = Conversation::factory()
        ->forSubject(ConversationSubject::adminSeller($admin->id, $seller->id))
        ->create();
    Message::factory()->from($seller)->unread()->create(['conversation_id' => $conversation->id]);

    $composerQueries = 0;
    DB::listen(function (QueryExecuted $query) use (&$composerQueries): void {
        $composerQueries += str_contains($query->sql, 'select (select count(*)') ? 1 : 0;
    });

    $response = $this->actingAs($admin, 'admin')->get('/admin/orders');

    $response->assertOk();
    expect($composerQueries)->toBe(1);
});

it('marks a section current on the rail from its show route, not only its index', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($this->admin(), 'admin')->get("/admin/sellers/{$seller->id}");

    $response->assertOk();
    $html = (string) $response->getContent();

    expect(preg_match_all('/<a\s+href="'.preg_quote(route('admin.sellers.index'), '/').'"\s+aria-current="page"/', $html))->toBe(2);
});

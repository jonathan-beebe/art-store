<?php

declare(strict_types=1);

namespace App\View\Composers;

use App\Actions\Messaging\MarkConversationRead;
use App\Domain\Messaging\ConversationSubject;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

it('counts the messages across every thread the admin has not read', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();
    $conversation = Conversation::factory()
        ->forSubject(ConversationSubject::adminSeller($admin->id, $seller->id))
        ->create();
    Message::factory()->from($seller)->unread()->create(['conversation_id' => $conversation->id]);
    Message::factory()->from($admin)->create(['conversation_id' => $conversation->id]);

    $response = $this->actingAs($admin, 'admin')->get('/admin');

    $response->assertSee('Messages (1)', escape: false);
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

    $response->assertDontSee('Messages (1)', escape: false);
});

it('carries the count onto every admin page without the controller passing it', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();
    $conversation = Conversation::factory()
        ->forSubject(ConversationSubject::adminSeller($admin->id, $seller->id))
        ->create();
    Message::factory()->from($seller)->unread()->create(['conversation_id' => $conversation->id]);

    $this->actingAs($admin, 'admin')->get('/admin/sellers')->assertSee('Messages (1)', escape: false);
    $this->actingAs($admin, 'admin')->get('/admin/customers')->assertSee('Messages (1)', escape: false);
});

it('renders a page with no admin signed in without the count', function (): void {
    $response = $this->get('/admin/login');

    $response->assertOk();
    $response->assertDontSee('Messages (', escape: false);
});

it('renders the xl-and-up nav rail with the active section marked (DSGN-006)', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')->get('/admin/orders');

    $response->assertOk();
    $html = (string) $response->getContent();

    // Three nav landmarks carry the twelve section links: the below-`xl`
    // inline nav, its Menu disclosure panel, and the `xl`-and-up rail.
    expect(substr_count($html, 'aria-label="Admin"'))->toBe(3);
    // The Orders link is marked current in all three — proof the rail
    // renders the same active-section logic as the two below-`xl` navs.
    expect(preg_match_all('/<a\s+href="'.preg_quote(route('admin.orders.index'), '/').'"\s+aria-current="page"/', $html))->toBe(3);
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

    expect(preg_match_all('/<a\s+href="'.preg_quote(route('admin.sellers.index'), '/').'"\s+aria-current="page"/', $html))->toBe(3);
});

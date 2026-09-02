<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Domain\DomainRuleViolation;
use App\Models\Admin;
use App\Models\Conversation;
use App\Notifications\ConversationResolved;
use Illuminate\Support\Facades\Notification;

it('marks a thread resolved with who resolved it', function (): void {
    $admin = Admin::factory()->create();
    $seller = $this->seller();
    $conversation = Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id]);

    $resolved = app(ResolveConversation::class)($conversation, $admin, $this->moment('2026-08-20 10:00:00'));

    expect($resolved->resolved_at?->format('Y-m-d H:i:s'))->toBe('2026-08-20 10:00:00')
        ->and($resolved->resolved_by_type)->toBe('admin')
        ->and($resolved->resolved_by_id)->toBe($admin->id);
});

it('refuses to resolve a thread already resolved', function (): void {
    $admin = Admin::factory()->create();
    $conversation = Conversation::factory()->adminSeller()->create([
        'resolved_at' => $this->moment('2026-08-19 09:00:00'),
    ]);

    $resolve = fn () => app(ResolveConversation::class)($conversation, $admin, $this->moment('2026-08-20 10:00:00'));

    expect($resolve)->toThrow(DomainRuleViolation::class, 'This thread is already resolved.');
});

it('notifies the seller when the desk resolves their thread', function (): void {
    Notification::fake();
    $admin = Admin::factory()->create();
    $seller = $this->seller();
    $conversation = Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id]);

    app(ResolveConversation::class)($conversation, $admin, $this->moment('2026-08-20 10:00:00'));

    Notification::assertSentTo($seller, ConversationResolved::class);
});

it('notifies nobody when the thread is missing its supported side', function (): void {
    Notification::fake();
    $admin = Admin::factory()->create();
    $conversation = Conversation::factory()->adminSeller()->create(['seller_id' => null]);

    app(ResolveConversation::class)($conversation, $admin, $this->moment('2026-08-20 10:00:00'));

    Notification::assertNothingSent();
});

it('notifies the customer when the seller resolves a listing question', function (): void {
    Notification::fake();
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create(['seller_id' => $seller->id, 'customer_id' => $customer->id]);

    app(ResolveConversation::class)($conversation, $seller, $this->moment('2026-08-20 10:00:00'));

    Notification::assertSentTo($customer, ConversationResolved::class);
});

<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Domain\DomainRuleViolation;
use App\Models\Admin;
use App\Models\Conversation;

it('reopens a resolved thread', function (): void {
    $admin = Admin::factory()->create();
    $conversation = Conversation::factory()->adminSeller()->create([
        'resolved_at' => $this->moment('2026-08-19 09:00:00'),
        'resolved_by_type' => 'admin',
        'resolved_by_id' => $admin->id,
    ]);

    $reopened = app(ReopenConversation::class)($conversation, $admin);

    expect($reopened->resolved_at)->toBeNull()
        ->and($reopened->resolved_by_type)->toBeNull()
        ->and($reopened->resolved_by_id)->toBeNull();
});

it('refuses to reopen a thread that is not resolved', function (): void {
    $admin = Admin::factory()->create();
    $conversation = Conversation::factory()->adminSeller()->create();

    $reopen = fn () => app(ReopenConversation::class)($conversation, $admin);

    expect($reopen)->toThrow(DomainRuleViolation::class, 'This thread is not resolved.');
});

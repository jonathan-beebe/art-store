<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use App\Domain\Auth\ActorType;
use DateTimeImmutable;

it('reads open from a null resolved_at', function (): void {
    expect(ConversationStatus::of(null))->toBe(ConversationStatus::Open);
});

it('reads resolved from a set resolved_at', function (): void {
    expect(ConversationStatus::of(new DateTimeImmutable('2026-08-20 09:00:00')))->toBe(ConversationStatus::Resolved);
});

it('stays open after any post', function (): void {
    expect(ConversationStatus::Open->afterPostBy(ActorType::Customer, ConversationKind::ListingQuestion))
        ->toBe(ConversationStatus::Open);
});

it('stays resolved when the supporting side posts', function (): void {
    expect(ConversationStatus::Resolved->afterPostBy(ActorType::Seller, ConversationKind::ListingQuestion))
        ->toBe(ConversationStatus::Resolved);
});

it('reopens when the supported side posts', function (): void {
    expect(ConversationStatus::Resolved->afterPostBy(ActorType::Customer, ConversationKind::ListingQuestion))
        ->toBe(ConversationStatus::Open);
});

it('reopens a resolved desk thread when the seller or customer posts, not when an admin does', function (): void {
    expect(ConversationStatus::Resolved->afterPostBy(ActorType::Admin, ConversationKind::AdminSeller))
        ->toBe(ConversationStatus::Resolved)
        ->and(ConversationStatus::Resolved->afterPostBy(ActorType::Seller, ConversationKind::AdminSeller))
        ->toBe(ConversationStatus::Open);
});

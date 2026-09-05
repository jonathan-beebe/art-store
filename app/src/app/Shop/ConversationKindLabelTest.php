<?php

declare(strict_types=1);

namespace App\Shop;

use App\Domain\Messaging\ConversationKind;

it('labels a listing question', function (): void {
    expect(ConversationKindLabel::of(ConversationKind::ListingQuestion))->toBe('Question');
});

it('labels a fulfillment thread', function (): void {
    expect(ConversationKindLabel::of(ConversationKind::Fulfillment))->toBe('Order');
});

it('labels both support kinds the same way', function (): void {
    expect(ConversationKindLabel::of(ConversationKind::AdminSeller))->toBe('Support')
        ->and(ConversationKindLabel::of(ConversationKind::AdminCustomer))->toBe('Support');
});

<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use App\Domain\Messaging\ConversationKind;

it('defaults to all', function (): void {
    expect(MessageDomain::default())->toBe(MessageDomain::All);
});

it('narrows to the buyer-facing kinds', function (): void {
    expect(MessageDomain::Buyers->kinds())->toBe([
        ConversationKind::ListingQuestion,
        ConversationKind::Fulfillment,
    ]);
});

it('narrows to the desk kind', function (): void {
    expect(MessageDomain::Support->kinds())->toBe([ConversationKind::AdminSeller]);
});

it('narrows to nothing, meaning every kind the seller participates in', function (): void {
    expect(MessageDomain::All->kinds())->toBeNull();
});

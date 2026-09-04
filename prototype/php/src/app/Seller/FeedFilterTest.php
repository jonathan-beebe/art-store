<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Seller\ActivityKind;

it('opens on All with nothing in the query', function (): void {
    $filter = FeedFilter::build('seller.orders.show', ['fulfillment' => 'ful_01', 'lane' => 'ship'], null);

    expect($filter->kind)->toBeNull()
        ->and($filter->links[0]->label)->toBe('All')
        ->and($filter->links[0]->active)->toBeTrue()
        ->and($filter->links[0]->href)->not->toContain('kind=');
});

it('offers one link per kind, in the order the feed merges them', function (): void {
    $filter = FeedFilter::build('seller.orders.show', ['fulfillment' => 'ful_01'], null);

    expect(array_map(fn (FeedKindLink $link): string => $link->label, $filter->links))
        ->toBe(['All', 'Browsing', 'Order', 'Shipping', 'Messages']);
});

it('marks the kind in force and carries the page filters beside it', function (): void {
    $filter = FeedFilter::build('seller.orders.show', ['fulfillment' => 'ful_01', 'lane' => 'done'], ActivityKind::Shipping);

    $shipping = collect($filter->links)->firstOrFail(fn (FeedKindLink $link): bool => $link->label === 'Shipping');

    expect($filter->kind)->toBe(ActivityKind::Shipping)
        ->and($shipping->active)->toBeTrue()
        ->and($shipping->href)->toContain('kind=shipping')
        ->and($shipping->href)->toContain('lane=done')
        ->and($filter->links[0]->active)->toBeFalse();
});

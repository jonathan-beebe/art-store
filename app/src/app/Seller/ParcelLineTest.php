<?php

declare(strict_types=1);

namespace App\Seller;

use App\Actions\Orders\FinalizeOrder;
use App\Domain\Listings\PlaceholderImage;
use App\Models\Fulfillment;

it('names the seller lines on the order as one phrase', function (): void {
    $seller = $this->seller();
    $order = $this->orderFor(
        $this->verifiedCustomer(),
        $this->listing($seller, ['title' => 'Harbour at Dusk']),
        $this->listing($seller, ['title' => 'Kiln Study']),
    );
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));

    expect(ParcelLine::label($order->fulfillments()->with('order.items')->sole()))->toBe('Harbour at Dusk +1 more');
});

it('says a parcel carrying none of the sellers lines has no items', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller('Rye Press'));
    $fulfillment->forceFill(['seller_id' => $this->seller('Blue Kiln Studio')->id])->save();

    $reloaded = Fulfillment::query()->with('order.items')->findOrFail($fulfillment->id);

    expect(ParcelLine::label($reloaded))->toBe('no items');
});

it('reads the sellers own line\'s picture for the parcel', function (): void {
    $seller = $this->seller();
    $listing = $this->listing($seller, ['title' => 'Harbour at Dusk']);
    $order = $this->orderFor($this->verifiedCustomer(), $listing);
    app(FinalizeOrder::class)($order, '4242424242424242', $this->moment('2026-08-20 10:00:00'));
    $fulfillment = $order->fulfillments()->with('order.items.listing')->sole();

    expect(ParcelLine::imageUrl($fulfillment))->toBe($listing->imageUrl());
});

it('falls back to a placeholder titled from the label when the parcel carries no items', function (): void {
    $fulfillment = $this->paidFulfillmentFor($this->seller('Rye Press'));
    $fulfillment->forceFill(['seller_id' => $this->seller('Blue Kiln Studio')->id])->save();
    $reloaded = Fulfillment::query()->with('order.items.listing')->findOrFail($fulfillment->id);

    expect(ParcelLine::imageUrl($reloaded))->toBe(PlaceholderImage::dataUri('no items'));
});

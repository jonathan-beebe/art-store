<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Actions\Listings\RecordListingEvent;
use App\Domain\Cart\CartQuantity;
use App\Domain\Configurator\CartLineFingerprint;
use App\Domain\Configurator\ConfiguredCartQuantity;
use App\Domain\Customers\CustomerStanding;
use App\Domain\Listings\ListingEventType;
use App\Logging\StoryEvent;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Listing;
use App\Models\Variant;
use App\Support\Story;
use DateTimeImmutable;

final readonly class AddToCart
{
    public function __construct(private RecordListingEvent $recordListingEvent) {}

    /**
     * @param  bool  $listingHasVariants  whether the listing holds any variant row at all — a listing with axes
     *                                    but no variant matching the current selection still takes this path,
     *                                    refused as unavailable rather than falling back to the legacy,
     *                                    variant-free stock check a listing with a modifier alone still uses
     * @param  list<array{axisId: string, axisName: string, optionValueId: string, optionValueLabel: string}>  $configuration  axis/value ids and labels, empty for a listing with no axes
     * @param  array<string, array{prompt: string, answer: string, raw: string}>  $answers  modifier id => {prompt, answer, raw}
     * @param  array<string, string>  $fingerprintAnswers  modifier id => raw answer, for {@see CartLineFingerprint}
     */
    public function __invoke(
        Cart $cart,
        Listing $listing,
        int $quantity,
        DateTimeImmutable $now,
        bool $listingHasVariants = false,
        ?Variant $variant = null,
        ?string $unitId = null,
        array $configuration = [],
        array $answers = [],
        array $fingerprintAnswers = [],
    ): CartItem {
        $fingerprint = CartLineFingerprint::of($variant?->id, $unitId, $fingerprintAnswers)->value;

        $item = $cart->items()->firstOrNew(['listing_id' => $listing->id, 'fingerprint' => $fingerprint]);
        $held = $item->quantity ?? 0;

        // A cart holds one line per (listing, configuration), so the second
        // time a shopper adds an identical configuration the line is raised
        // rather than added.
        $raises = $item->exists;

        return Story::for($raises ? StoryEvent::CartUpdate : StoryEvent::CartAdd)->tell(
            $raises ? 'raising a cart line' : 'adding a listing to the cart',
            [
                'cart_id' => $cart->id,
                'listing_id' => $listing->id,
                'variant_id' => $variant?->id,
                'unit_id' => $unitId,
                'quantity' => $held + $quantity,
            ],
            function (Story $story) use ($cart, $listing, $item, $held, $quantity, $raises, $now, $listingHasVariants, $variant, $unitId, $configuration, $answers, $fingerprint): CartItem {
                CustomerStanding::assertCanShop($cart->loadMissing('customer')->customer->blockReason());

                $item->quantity = $listingHasVariants
                    ? ConfiguredCartQuantity::withinStock(
                        $held + $quantity,
                        $variant !== null && $variant->availability()->available,
                        $variant !== null && $variant->is_serialized,
                        $variant?->quantity,
                    )
                    : CartQuantity::withinStock($held + $quantity, $listing->quantity, $listing->status, $listing->hasActiveRemoval());
                $item->variant_id = $variant?->id;
                $item->unit_id = $unitId;
                $item->configuration_json = $configuration === [] ? null : $configuration;
                $item->answers_json = $answers === [] ? null : $answers;
                $item->fingerprint = $fingerprint;
                $item->save();

                ($this->recordListingEvent)($listing, $cart->customer_id, ListingEventType::CartAdd, $now);

                $story->did($raises ? 'raised the cart line' : 'added the listing to the cart', [
                    'cart_id' => $cart->id,
                    'cart_item_id' => $item->id,
                    'listing_id' => $listing->id,
                    'quantity' => $item->quantity,
                ]);

                return $item;
            },
        );
    }
}

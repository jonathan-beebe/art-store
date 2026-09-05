<?php

declare(strict_types=1);

namespace App\Actions\Cart;

use App\Analytics\Analytics;
use App\Analytics\AnalyticsEvent;
use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Cart\CartQuantity;
use App\Domain\Configurator\CartLineFingerprint;
use App\Domain\Configurator\ConfiguredCartQuantity;
use App\Domain\Customers\CustomerStanding;
use App\Logging\StoryEvent;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Listing;
use App\Models\Variant;
use App\Support\Story;
use DateTimeImmutable;

final readonly class AddToCart
{
    public function __construct(private Analytics $analytics) {}

    /**
     * @param  bool  $listingHasVariants  whether the listing holds any variant row at all — a listing with axes
     *                                    but no variant matching the current selection still takes this path
     *                                    and reads as unavailable. A listing with only a modifier resolves
     *                                    through the legacy, variant-free stock check.
     * @param  list<array{axisId: string, axisName: string, optionValueId: string, optionValueLabel: string}>  $configuration  axis/value ids and labels, empty for a listing lacking axes
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

        $item = $cart->items()->firstOrNew(['listing_id' => $listing->id, 'fingerprint' => $fingerprint], ['customer_id' => $cart->customer_id]);
        $held = $item->quantity ?? 0;

        // A cart holds one line per (listing, configuration). The second
        // time a shopper adds an identical configuration, this raises the
        // existing line's quantity.
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

                $item->quantity = $this->resolveQuantity($listingHasVariants, $listing, $variant, $held + $quantity);
                $item->variant_id = $variant?->id;
                $item->unit_id = $unitId;
                $item->configuration_json = $configuration === [] ? null : $configuration;
                $item->answers_json = $answers === [] ? null : $answers;
                $item->fingerprint = $fingerprint;
                $item->save();

                $this->analytics->recordEvent(AnalyticsEvent::forListing(AnalyticsEventName::ListingCartAdd, $listing->id, $cart->customer_id, $now));

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

    /** {@see AddToCart::__invoke()}'s `$listingHasVariants` doc explains why
     * a listing routes to one stock check or the other, never both. */
    private function resolveQuantity(bool $listingHasVariants, Listing $listing, ?Variant $variant, int $requestedQuantity): int
    {
        return $listingHasVariants
            ? ConfiguredCartQuantity::withinStock(
                $requestedQuantity,
                $variant !== null && $variant->availability()->available,
                $variant !== null && $variant->is_serialized,
                $variant?->quantity,
            )
            : CartQuantity::withinStock($requestedQuantity, $listing->quantity, $listing->status, $listing->hasActiveRemoval());
    }
}

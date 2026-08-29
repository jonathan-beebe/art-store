<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Cart\CartLine;
use App\Domain\Configurator\OptionAvailability;
use App\Domain\Configurator\PriceBreakdown;
use App\Models\Concerns\HasPrefixedUlid;
use App\Support\Configurator\ConfigurationPricer;
use Database\Factories\CartItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property-read Cart $cart
 * @property-read Listing $listing
 * @property-read Variant|null $variant
 * @property-read Unit|null $unit
 * @property list<array{axisId: string, axisName: string, optionValueId: string, optionValueLabel: string}>|null $configuration_json
 * @property array<string, array{prompt: string, answer: string, raw: string}>|null $answers_json
 */
#[Fillable(['cart_id', 'listing_id', 'variant_id', 'unit_id', 'quantity', 'configuration_json', 'answers_json', 'fingerprint'])]
class CartItem extends Model
{
    /** @use HasFactory<CartItemFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'cti';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'configuration_json' => 'array',
            'answers_json' => 'array',
        ];
    }

    /** @return BelongsTo<Cart, $this> */
    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<Variant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(Variant::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function hasVariant(): bool
    {
        return $this->variant_id !== null;
    }

    public function toLine(): CartLine
    {
        $breakdown = $this->currentBreakdown();

        return CartLine::ofBreakdownTotal($this->listing->seller_id, $this->listing->price(), $this->quantity, $breakdown->total());
    }

    /**
     * The itemized price this line resolves to right now — never stored, so a
     * price change on the listing or its variant since add-time shows up the
     * next time the cart renders.
     */
    public function currentBreakdown(): PriceBreakdown
    {
        $variant = $this->hasVariant() ? $this->variant()->with('options.optionValue')->firstOrFail() : null;
        $selectedOptionValues = $variant === null
            ? []
            : array_values($variant->options->map(fn (VariantOption $option): ?OptionValue => $option->optionValue)->filter()->all());

        return ConfigurationPricer::price(
            $this->listing,
            $selectedOptionValues,
            $variant,
            $this->unit_id === null ? null : $this->unit()->first(),
            $this->rawAnswers(),
            $this->quantity,
        );
    }

    /**
     * Whether the exact configuration this line holds can still be bought —
     * a variant an admin or seller has since disabled or sold through reads
     * as unavailable here the same way the configurator page greys it out.
     */
    public function currentAvailability(): OptionAvailability
    {
        if (! $this->hasVariant()) {
            return OptionAvailability::selectable();
        }

        $variant = $this->variant()->firstOrFail();

        if (! $variant->enabled) {
            return OptionAvailability::notOffered();
        }

        return $variant->availability()->available ? OptionAvailability::selectable() : OptionAvailability::outOfStock();
    }

    /**
     * @return array<string, string>
     */
    private function rawAnswers(): array
    {
        $answers = [];

        foreach ($this->answers_json ?? [] as $modifierId => $answer) {
            $answers[$modifierId] = $answer['raw'];
        }

        return $answers;
    }
}

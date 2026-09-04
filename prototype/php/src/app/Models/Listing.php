<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Configurator\ConfiguratorPublishValidation;
use App\Domain\Configurator\PricingMode;
use App\Domain\Configurator\PublishIssue;
use App\Domain\Configurator\StandaloneOptionSnapshot;
use App\Domain\Configurator\VariantSnapshot;
use App\Domain\Listings\ListingAvailability;
use App\Domain\Listings\ListingStatus;
use App\Domain\Listings\ListingStock;
use App\Domain\Listings\ListingStockLabel;
use App\Domain\Listings\RemovedFilter;
use App\Domain\Money\Money;
use App\Models\Concerns\HasPrefixedUlid;
use App\Support\PlaceholderImage;
use Database\Factories\ListingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Override;

/**
 * @property-read Seller $seller
 * @property-read int $tally  only on a row the `countedByStatus` scope selected
 */
#[Fillable([
    'seller_id', 'category_id', 'fulfillment_flow_id', 'title', 'slug', 'description',
    'price_cents', 'quantity', 'status', 'dimensions',
])]
class Listing extends Model
{
    /** @use HasFactory<ListingFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'lst';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'status' => ListingStatus::class,
            'price_cents' => 'integer',
            'quantity' => 'integer',
        ];
    }

    /** @return BelongsTo<Seller, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The flow this piece ships by. Null hands the parcel to the seller's
     * default flow.
     *
     * @return BelongsTo<FulfillmentFlow, $this>
     */
    public function fulfillmentFlow(): BelongsTo
    {
        return $this->belongsTo(FulfillmentFlow::class);
    }

    /** @return HasMany<ListingAttribute, $this> */
    public function listingAttributes(): HasMany
    {
        return $this->hasMany(ListingAttribute::class);
    }

    /** @return HasMany<OptionAxis, $this> */
    public function optionAxes(): HasMany
    {
        return $this->hasMany(OptionAxis::class);
    }

    /** @return HasMany<Variant, $this> */
    public function variants(): HasMany
    {
        return $this->hasMany(Variant::class);
    }

    /** @return HasMany<Modifier, $this> */
    public function modifiers(): HasMany
    {
        return $this->hasMany(Modifier::class);
    }

    /** @return HasMany<QuantityBreak, $this> */
    public function quantityBreaks(): HasMany
    {
        return $this->hasMany(QuantityBreak::class);
    }

    /** @return HasMany<DescriptionSection, $this> */
    public function descriptionSections(): HasMany
    {
        return $this->hasMany(DescriptionSection::class);
    }

    /** @return HasMany<ListingImage, $this> */
    public function images(): HasMany
    {
        return $this->hasMany(ListingImage::class);
    }

    /** @return HasMany<Favorite, $this> */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
    }

    /** @return HasMany<OrderItem, $this> */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<ListingFaq, $this> */
    public function faqs(): HasMany
    {
        return $this->hasMany(ListingFaq::class);
    }

    /** @return HasMany<ListingRemoval, $this> */
    public function removals(): HasMany
    {
        return $this->hasMany(ListingRemoval::class);
    }

    /** @return HasOne<ListingRemoval, $this> */
    public function activeRemoval(): HasOne
    {
        return $this->removals()->one()->whereNull('lifted_at')->latestOfMany('created_at');
    }

    /**
     * A fresh read rather than the loaded relation, so a caller that never
     * eager-loaded `activeRemoval` still gets an answer under strict mode —
     * load-bearing for a caller that checks, writes a removal, then checks
     * this same instance again in the same request (moderation actions and
     * their tests both do).
     */
    public function currentRemoval(): ?ListingRemoval
    {
        return $this->activeRemoval()->first();
    }

    public function hasActiveRemoval(): bool
    {
        return $this->currentRemoval() !== null;
    }

    /**
     * The admin's own words for why this listing is off the storefront, for
     * the seller's own page. Null when nothing is removed.
     */
    public function removalReason(): ?string
    {
        return $this->currentRemoval()?->reason;
    }

    public function price(): Money
    {
        return Money::fromCents($this->price_cents);
    }

    /**
     * The bare count, or "Made to order" for the null, uncapped reading a
     * seller reaches through the "Made to order" checkbox.
     */
    public function quantityLabel(): string
    {
        return ListingStockLabel::bare($this->quantity);
    }

    /**
     * Whether this listing still prices and stocks itself, rather than
     * handing that job to the choices or combinations screens — true until
     * it either offers an option choice or breaks into serialized,
     * one-of-a-kind pieces. A modifier or a quantity discount alone leaves
     * this true: neither replaces the listing's own price and stock count,
     * only adjusts or discounts it.
     */
    public function hasOwnPriceAndStock(): bool
    {
        return $this->optionAxes()->doesntExist() && $this->variants()->where('is_serialized', true)->doesntExist();
    }

    public function isPurchasable(): bool
    {
        return ListingAvailability::isPurchasable($this->status, $this->quantity);
    }

    /**
     * Whether `/art/{slug}` answers this listing or a 404 — a removal
     * outranks whatever `status` says, the same as browse and search.
     */
    public function isOnStorefront(): bool
    {
        return ListingAvailability::isOnStorefront($this->status, $this->hasActiveRemoval());
    }

    /**
     * The transitions the seller's own page offers right now.
     *
     * @return list<ListingStatus>
     */
    public function availableTransitions(): array
    {
        return ListingAvailability::availableTransitions($this->status, $this->hasActiveRemoval());
    }

    /**
     * The same transitions {@see self::availableTransitions()} computes, but
     * read off an eager-loaded `activeRemoval` relation instead of a fresh
     * `hasActiveRemoval()` query — for a caller rendering many rows at once
     * (the seller listings index) that already eager-loaded the relation
     * across the whole set. Falls back to the fresh check when the relation
     * was not eager-loaded, so a caller that skips the eager load still gets
     * a correct answer, just without the saving.
     *
     * @return list<ListingStatus>
     */
    public function availableTransitionsFromEagerLoad(): array
    {
        $hasActiveRemoval = $this->relationLoaded('activeRemoval') ? $this->activeRemoval !== null : $this->hasActiveRemoval();

        return ListingAvailability::availableTransitions($this->status, $hasActiveRemoval);
    }

    /**
     * Folds this listing's axes, variants, modifiers, quantity breaks, and
     * sections into the primitives {@see ConfiguratorPublishValidation} judges
     * without reading anything itself. Empty for a listing with no
     * configurator data — the legacy, axis-free path has nothing to check.
     *
     * @return list<PublishIssue>
     */
    public function publishIssues(): array
    {
        $axes = $this->optionAxes()->withCount('optionValues')->with('optionValues')->get();

        $variants = array_values($this->variants()->get()->map(fn (Variant $variant): VariantSnapshot => new VariantSnapshot(
            $variant->id,
            $variant->enabled,
            $variant->resolvedPrice($this->price())->cents,
            $variant->is_serialized,
            $variant->availableUnitCount(),
            $variant->axisIdsCovered(),
        ))->all());

        /** @var list<string> $requiredAttributePropertyIds */
        $requiredAttributePropertyIds = $this->category_id === null ? [] : array_values(CategoryProperty::query()
            ->where('category_id', $this->category_id)
            ->where('usable_as_attribute', true)
            ->where('required', true)
            ->pluck('property_id')
            ->all());

        /** @var list<string> $attributedPropertyIds */
        $attributedPropertyIds = array_values($this->listingAttributes()->distinct()->pluck('property_id')->all());

        $standaloneOptions = array_values($axes
            ->filter(fn (OptionAxis $axis): bool => $axis->pricing_mode === PricingMode::Standalone)
            ->flatMap(fn (OptionAxis $axis) => $axis->optionValues)
            ->map(fn (OptionValue $value): StandaloneOptionSnapshot => new StandaloneOptionSnapshot($value->id, $value->price_cents))
            ->all());

        return ConfiguratorPublishValidation::check(
            axisIds: array_values($axes->map(fn (OptionAxis $axis): string => $axis->id)->all()),
            optionCountsPerAxis: array_values($axes->map(function (OptionAxis $axis): int {
                $count = $axis->getAttribute('option_values_count');

                return is_numeric($count) ? (int) $count : 0;
            })->all()),
            variants: $variants,
            modifierCount: $this->modifiers()->count(),
            quantityBreakCount: $this->quantityBreaks()->count(),
            sectionCount: $this->descriptionSections()->count(),
            requiredAttributePropertyIds: $requiredAttributePropertyIds,
            attributedPropertyIds: $attributedPropertyIds,
            standaloneOptions: $standaloneOptions,
        );
    }

    /**
     * Whether the full cross product of this listing's axes already has a
     * variant row for every combination — what {@see \App\Actions\Configurator\GenerateVariants}
     * would produce from a clean slate. An axis-free listing has no
     * combination to exhaust, so it reads false regardless of its one
     * legacy variant.
     */
    public function everyVariantCombinationExists(): bool
    {
        $axes = $this->optionAxes()->withCount('optionValues')->get();

        if ($axes->isEmpty()) {
            return false;
        }

        $combinationCount = $axes->reduce(function (int $total, OptionAxis $axis): int {
            $count = $axis->getAttribute('option_values_count');

            return $total * (is_numeric($count) ? (int) $count : 0);
        }, 1);

        return $this->variants()->count() >= $combinationCount;
    }

    /**
     * Hands the given number of items to a buyer, reaching `sold` at nothing
     * left.
     */
    public function sell(int $quantity): self
    {
        return $this->applyStock(ListingStock::afterSale($this->quantity, $this->status, $quantity, $this->title));
    }

    /**
     * Puts items a sale took back on the shelf, and a sold-out listing back on
     * the storefront.
     */
    public function restock(int $quantity): self
    {
        return $this->applyStock(ListingStock::afterRestock($this->quantity, $this->status, $quantity));
    }

    public function changeStatusTo(ListingStatus $next): self
    {
        $this->update(['status' => $this->status->transitionTo($next)]);

        return $this;
    }

    /**
     * The one place quantity and status are written together, so the pair the
     * core decided on is the pair the row holds.
     */
    private function applyStock(ListingStock $stock): self
    {
        $this->update(['quantity' => $stock->quantity, 'status' => $stock->status]);

        return $this;
    }

    /**
     * The cover — the lowest-position row in `images` — or a placeholder
     * drawn from the title when the listing carries no image yet. A caller
     * that eager-loaded `images` ordered by `position` is read from that
     * loaded collection rather than issuing a fresh query; a caller that
     * never eager-loaded it still gets an answer, via a query of its own,
     * under strict mode.
     */
    public function imageUrl(): string
    {
        $cover = $this->relationLoaded('images')
            ? $this->images->first()
            : $this->images()->orderBy('position')->first();

        return $cover === null
            ? PlaceholderImage::dataUri($this->title)
            : $cover->url();
    }

    /**
     * The storefront's own listing set: for sale, and clear of any active
     * removal — a removed listing stays `for_sale` in the row, so status
     * alone is not enough to keep it out of browse and search.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function forSale(Builder $query): void
    {
        $query->where($query->qualifyColumn('status'), ListingStatus::ForSale)->notRemoved();
    }

    /**
     * Everything a customer can still reach through the storefront: on it by
     * status, and clear of any active removal. `isOnStorefront` answers this
     * for one row in hand; the pages that turn a set of rows into listings —
     * the favorites page among them — ask it here, so a removal takes a
     * listing off all of them at once.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function onStorefront(Builder $query): void
    {
        $query->whereIn($query->qualifyColumn('status'), ListingAvailability::storefrontStatuses())->notRemoved();
    }

    /**
     * The removal half of `isOnStorefront`, in the only dialect a `where`
     * clause speaks. Every query that keeps removed listings out spells the
     * rule through this one, so lifting a removal puts the listing back
     * everywhere at once.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function notRemoved(Builder $query): void
    {
        $query->whereDoesntHave('removals', fn (Builder $removals): Builder => $removals->whereNull('lifted_at'));
    }

    /**
     * Takes the rows this query selects for update. Placement reads a
     * listing's quantity and status and writes the pair back from what it
     * read, so the row has to be held from that read until the transaction
     * commits — otherwise two shoppers both read `quantity = 1`, both pass the
     * plan, and the second `UPDATE` overwrites the first with its own stale
     * arithmetic. In id order, so two carts holding the same listings ask for
     * them in the same order. SQLite, which the prototype develops and tests
     * on, has no row lock and serialises writers instead; its grammar compiles
     * the clause away.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function lockedForPlacement(Builder $query): void
    {
        $query->orderBy('id')->lockForUpdate();
    }

    /**
     * Takes the rows a moderation decision is judged against for update. A
     * removal is refused when one already stands, and that check reads the
     * `listing_removals` table rather than this row, so nothing there keeps
     * two admins apart: both read no active removal and both insert one. The
     * listing row they each have to take first is what serialises them.
     * SQLite, which the prototype develops and tests on, has no row lock and
     * serialises writers instead; its grammar compiles the clause away.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function lockedForModeration(Builder $query): void
    {
        $query->lockForUpdate();
    }

    /**
     * Re-reads this row for update inside the caller's transaction, the way
     * `Fulfillment::takeForTransition` re-reads the row a transition is
     * judged against.
     */
    public function takeForModeration(): static
    {
        /** @var static $locked */
        $locked = $this->newQuery()->whereKey($this->getKey())->lockedForModeration()->sole();

        return $this->setRawAttributes($locked->getAttributes(), sync: true);
    }

    /**
     * The admin listings list, narrowed to one status. A null filter adds no
     * clause, which is what the console's "All statuses" submits.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function ofStatus(Builder $query, ?ListingStatus $status): void
    {
        if ($status instanceof ListingStatus) {
            $query->where('status', $status);
        }
    }

    /**
     * The same list narrowed to one seller. A seller id naming nobody selects
     * nothing rather than everything.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function ofSeller(Builder $query, ?string $sellerId): void
    {
        if ($sellerId !== null) {
            $query->where('seller_id', $sellerId);
        }
    }

    /**
     * The storefront media filter (FEAT-030): a listing carrying a Medium
     * attribute whose label matches the URL's lowercase value (Ceramic →
     * `medium=ceramic`). Null adds no clause, the same "empty means all"
     * idiom `ofStatus` and `ofSeller` hold; a value nothing carries keeps
     * this scope to zero rows rather than falling back to no filter — the
     * same emptiness an unrecognised legacy medium produced.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function ofMediumAttribute(Builder $query, ?string $medium): void
    {
        if ($medium === null) {
            return;
        }

        $valueIds = PropertyValue::query()
            ->whereHas('property', fn (Builder $properties): Builder => $properties->where('name', 'Medium'))
            ->get()
            ->filter(fn (PropertyValue $value): bool => mb_strtolower($value->label) === mb_strtolower($medium))
            ->pluck('id');

        $query->whereHas('listingAttributes', fn (Builder $attributes): Builder => $attributes->whereIn('property_value_id', $valueIds));
    }

    /**
     * The storefront search filter (/search): a listing whose title,
     * description, or Medium attribute label matches the given LIKE pattern
     * — {@see \App\Domain\Shop\ListingSearch::likePattern()} builds it, so
     * this scope takes the pattern rather than the raw term and stays free
     * of the wildcard-escaping rule the domain owns. Null adds no clause,
     * the same "empty means all" idiom `ofMediumAttribute` holds.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function ofSearchTerm(Builder $query, ?string $likePattern): void
    {
        if ($likePattern === null) {
            return;
        }

        $query->where(fn (Builder $match): Builder => $match
            ->where('title', 'like', $likePattern)
            ->orWhere('description', 'like', $likePattern)
            ->orWhereHas('listingAttributes', fn (Builder $attributes): Builder => $attributes
                ->whereHas('property', fn (Builder $properties): Builder => $properties->where('name', 'Medium'))
                ->whereHas('propertyValue', fn (Builder $values): Builder => $values->where('label', 'like', $likePattern))));
    }

    /**
     * The storefront category filter (/browse/{categoryPath}): a listing
     * placed in the given category or one of its descendants. Categories are
     * matched in PHP rather than a SQL LIKE, the same idiom `ofMediumAttribute`
     * holds for medium labels — `path` is a materialized path
     * (`/jewelry/rings/`) and a descendant's starts with its ancestor's, so
     * `str_starts_with` reads the tree without walking `parent_id`. Null adds
     * no clause, the same "empty means all" idiom every `of*` scope holds.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function ofCategoryPathPrefix(Builder $query, ?string $pathPrefix): void
    {
        if ($pathPrefix === null) {
            return;
        }

        $categoryIds = Category::query()
            ->get(['id', 'path'])
            ->filter(fn (Category $category): bool => str_starts_with($category->path, $pathPrefix))
            ->pluck('id');

        $query->whereIn($query->qualifyColumn('category_id'), $categoryIds);
    }

    /**
     * The listing page's Medium line: the label of this listing's Medium
     * attribute, or null when it does not carry one — `listing_attributes` is
     * the only place a listing's medium lives (RFCTR-009).
     */
    public function mediumAttributeLabel(): ?string
    {
        return $this->listingAttributes()
            ->whereHas('property', fn (Builder $properties): Builder => $properties->where('name', 'Medium'))
            ->with('propertyValue')
            ->first()
            ?->propertyValue
            ->label;
    }

    /**
     * The same list narrowed by removal state. `null` and `Any` both add no
     * clause, which is what an absent, empty, or `removed=any` filter asks
     * for — the same "empty means all" shape `ofStatus` and `ofSeller` hold.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function ofRemoval(Builder $query, ?RemovedFilter $removed): void
    {
        match ($removed) {
            null, RemovedFilter::Any => null,
            RemovedFilter::Removed => $query->whereHas('removals', fn (Builder $removals): Builder => $removals->whereNull('lifted_at')),
            RemovedFilter::Visible => $query->notRemoved(),
        };
    }

    /**
     * One row per status the seller's listings hold, carrying how many hold it.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function countedByStatus(Builder $query): void
    {
        $query->select('status')
            ->selectRaw('count(*) as tally')
            ->groupBy('status');
    }

    /**
     * The same tally, across every seller — `/admin`'s listing count.
     *
     * @return array<string, int> status value => count
     */
    public static function platformCountsByStatus(): array
    {
        $counts = [];

        foreach (self::query()->countedByStatus()->get() as $row) {
            $counts[$row->status->value] = $row->tally;
        }

        return $counts;
    }
}

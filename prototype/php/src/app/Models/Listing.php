<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Configurator\ConfiguratorPublishValidation;
use App\Domain\Configurator\PublishIssue;
use App\Domain\Configurator\VariantSnapshot;
use App\Domain\Listings\ListingAvailability;
use App\Domain\Listings\ListingEventType;
use App\Domain\Listings\ListingStatus;
use App\Domain\Listings\ListingStock;
use App\Domain\Listings\RemovedFilter;
use App\Domain\Money\Money;
use App\Models\Concerns\HasPrefixedUlid;
use App\Support\PlaceholderImage;
use Closure;
use Database\Factories\ListingFactory;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Override;

/**
 * @property-read Seller $seller
 * @property-read int $tally  only on a row the `countedByStatus` scope selected
 * @property-read int $views_count  only after `withEventCounts` or `loadEventCounts`
 * @property-read int $favorites_count  only after `withEventCounts` or `loadEventCounts`
 * @property-read int $cart_adds_count  only after `withEventCounts` or `loadEventCounts`
 */
#[Fillable([
    'seller_id', 'category_id', 'title', 'slug', 'description', 'price_cents',
    'quantity', 'status', 'image_path', 'medium', 'dimensions',
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

    /** @return HasMany<ListingEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(ListingEvent::class);
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
     * eager-loaded `activeRemoval` still gets an answer under strict mode.
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
     * Folds this listing's axes, variants, modifiers, quantity breaks, and
     * sections into the primitives {@see ConfiguratorPublishValidation} judges
     * without reading anything itself. Empty for a listing with no
     * configurator data — the legacy, axis-free path has nothing to check.
     *
     * @return list<PublishIssue>
     */
    public function publishIssues(): array
    {
        $axes = $this->optionAxes()->withCount('optionValues')->get();

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
        );
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

    public function imageUrl(): string
    {
        return $this->image_path === null
            ? PlaceholderImage::dataUri($this->title)
            : Storage::disk('public')->url($this->image_path);
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

    /** @param Builder<$this> $query */
    #[Scope]
    protected function withEventCounts(Builder $query): void
    {
        $query->withCount(self::eventCounts());
    }

    /**
     * The same three counts the `withEventCounts` scope selects, for a listing
     * already in hand — a route-bound model, say.
     */
    public function loadEventCounts(): self
    {
        $this->loadCount(self::eventCounts());

        return $this;
    }

    /**
     * How many events of each type the listing recorded on each day from $from
     * onward, grouped by the database.
     *
     * @return array<string, array<string, int>> day (Y-m-d) => event type value => count
     */
    public function eventCountsByDateSince(DateTimeImmutable $from): array
    {
        $counts = [];

        foreach ($this->events()->dailyCountsSince($from)->get() as $row) {
            $counts[$row->day][$row->type->value] = $row->tally;
        }

        return $counts;
    }

    /**
     * @return array<string, Closure(Builder<ListingEvent>): Builder<ListingEvent>>
     */
    private static function eventCounts(): array
    {
        return [
            'events as views_count' => fn (Builder $events) => $events->where('type', ListingEventType::View),
            'events as favorites_count' => fn (Builder $events) => $events->where('type', ListingEventType::Favorite),
            'events as cart_adds_count' => fn (Builder $events) => $events->where('type', ListingEventType::CartAdd),
        ];
    }
}

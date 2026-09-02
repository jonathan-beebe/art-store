<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Listings\ListingEventType;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\ListingEventFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * One row per interaction (a view, a favorite, a cart add) a listing
 * collects, in the analytics store (config/database.php).
 *
 * @property-read string $day  only on a row the `dailyCountsSince` scope selected
 * @property-read int $tally  only on a row the `dailyCountsSince` scope selected
 */
#[Fillable(['listing_id', 'seller_id', 'customer_id', 'type', 'occurred_at'])]
class ListingEvent extends Model
{
    /** @use HasFactory<ListingEventFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    protected $connection = 'analytics';

    public static function idPrefix(): string
    {
        return 'lev';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'type' => ListingEventType::class,
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * Eloquent's default `newRelatedInstance()` hands a related model this
     * model's own connection when the related model names none of its own.
     * Listing, Seller, and Customer live on the default connection and must
     * keep it when reached from a row that lives on the analytics one.
     *
     * @template TRelatedModel of Model
     *
     * @param  class-string<TRelatedModel>  $class
     * @return TRelatedModel
     */
    #[Override]
    protected function newRelatedInstance($class)
    {
        return new $class;
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<Seller, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * One row per (day, type) from $from onward, carrying the count as `tally`
     * and the day as `day`.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function dailyCountsSince(Builder $query, DateTimeInterface $from): void
    {
        $query->where('occurred_at', '>=', $from)
            ->select('type')
            ->selectRaw('date(occurred_at) as day')
            ->selectRaw('count(*) as tally')
            ->groupBy('day', 'type');
    }

    /**
     * How many events of each type the whole platform has recorded, across
     * every listing — `/admin/stats`'s tally.
     *
     * @return array<string, int> event type value => count
     */
    public static function platformCountsByType(): array
    {
        $counts = [];

        foreach (self::query()->select('type')->selectRaw('count(*) as tally')->groupBy('type')->get() as $row) {
            $counts[$row->type->value] = $row->tally;
        }

        return $counts;
    }

    /**
     * How many events of each type one listing has recorded —
     * {@see Listing::loadEventCounts()}'s source.
     *
     * @return array<string, int> event type value => count
     */
    public static function countsForListing(string $listingId): array
    {
        $counts = [];

        foreach (self::query()->where('listing_id', $listingId)->select('type')->selectRaw('count(*) as tally')->groupBy('type')->get() as $row) {
            $counts[$row->type->value] = $row->tally;
        }

        return $counts;
    }
}

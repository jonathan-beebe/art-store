<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Listings\ListingEventType;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property-read string $day  only on a row the `dailyCountsSince` scope selected
 * @property-read int $tally  only on a row the `dailyCountsSince` scope selected
 */
#[Fillable(['listing_id', 'customer_id', 'type', 'occurred_at'])]
class ListingEvent extends Model
{
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

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
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
}

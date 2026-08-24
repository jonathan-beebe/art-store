<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Escrow\LedgerBalances;
use App\Domain\Escrow\LedgerEntryType;
use App\Domain\Escrow\LedgerMovement;
use App\Domain\Money\Money;
use App\Models\Concerns\HasPrefixedUlid;
use App\Observers\LedgerEntryObserver;
use Database\Factories\LedgerEntryFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;
use Override;

/**
 * @property-read Seller $seller
 */
#[Fillable(['seller_id', 'fulfillment_id', 'payout_id', 'type', 'amount_cents', 'occurred_at'])]
#[ObservedBy(LedgerEntryObserver::class)]
class LedgerEntry extends Model
{
    /** @use HasFactory<LedgerEntryFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'led';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'type' => LedgerEntryType::class,
            'amount_cents' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Seller, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /** @return BelongsTo<Fulfillment, $this> */
    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    /** @return BelongsTo<Payout, $this> */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    public function amount(): Money
    {
        return Money::fromCents($this->amount_cents);
    }

    public function toMovement(): LedgerMovement
    {
        return LedgerMovement::of($this->type, $this->amount(), $this->fulfillment_id);
    }

    /**
     * Every seller's balance from one read of the whole ledger: the database
     * sums each (seller, type) pair and the fold turns the summed rows behind
     * a seller into their balance. This is what a page listing sellers reads,
     * so the number of queries it costs does not follow the number of sellers
     * on it.
     */
    public static function balancesBySeller(): LedgerBalances
    {
        /** @var array<string, list<LedgerMovement>> $movements */
        $movements = self::query()
            ->totalledByType()
            ->get()
            ->groupBy('seller_id')
            ->map(fn (Collection $entries): array => array_values(
                $entries->map(fn (self $entry): LedgerMovement => $entry->toMovement())->all(),
            ))
            ->all();

        return LedgerBalances::from($movements);
    }

    /** @param Builder<$this> $query */
    #[Scope]
    protected function occurredBy(Builder $query, DateTimeInterface $moment): void
    {
        $query->where('occurred_at', '<=', $moment);
    }

    /**
     * One row per (seller, fulfillment, type), its `amount_cents` the sum the
     * database added up. A ledger fold only ever adds amounts of the same
     * type on the same fulfillment together, so one summed row per pair
     * stands in for every entry behind it — and the fulfillment stays in the
     * grouping because a refund nets against its own sale's hold or release
     * (`LedgerBalance::from()`), never against another's.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function totalledByType(Builder $query): void
    {
        $query->select('seller_id', 'fulfillment_id', 'type')
            ->selectRaw('sum(amount_cents) as amount_cents')
            ->groupBy('seller_id', 'fulfillment_id', 'type');
    }

    /**
     * The ledger browser and the seller's earnings page, narrowed to one
     * kind of movement. A null filter adds no clause.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function ofType(Builder $query, ?LedgerEntryType $type): void
    {
        if ($type instanceof LedgerEntryType) {
            $query->where('type', $type);
        }
    }
}

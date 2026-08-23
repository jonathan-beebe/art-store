<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Escrow\LedgerEntryType;
use App\Domain\Escrow\LedgerMovement;
use App\Domain\Money\Money;
use Database\Factories\LedgerEntryFactory;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Override;

/**
 * @property-read Seller $seller
 */
#[Fillable(['seller_id', 'fulfillment_id', 'payout_id', 'type', 'amount_cents', 'occurred_at'])]
class LedgerEntry extends Model
{
    /** @use HasFactory<LedgerEntryFactory> */
    use HasFactory;

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
        return LedgerMovement::of($this->type, $this->amount());
    }

    /** @param Builder<$this> $query */
    #[Scope]
    protected function occurredBy(Builder $query, DateTimeInterface $moment): void
    {
        $query->where('occurred_at', '<=', $moment);
    }

    /**
     * One row per (seller, type), its `amount_cents` the sum the database
     * added up. A ledger fold only ever adds amounts of the same type
     * together, so one summed row per type stands in for every entry behind
     * it.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function totalledByType(Builder $query): void
    {
        $query->select('seller_id', 'type')
            ->selectRaw('sum(amount_cents) as amount_cents')
            ->groupBy('seller_id', 'type');
    }
}

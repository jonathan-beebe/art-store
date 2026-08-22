<?php

namespace App\Models;

use App\Domain\Escrow\LedgerEntryType;
use App\Domain\Escrow\LedgerMovement;
use App\Domain\Money\Money;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['seller_id', 'fulfillment_id', 'payout_id', 'type', 'amount_cents', 'occurred_at'])]
class LedgerEntry extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => LedgerEntryType::class,
            'amount_cents' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(Payout::class);
    }

    public function toMovement(): LedgerMovement
    {
        return LedgerMovement::of($this->type, Money::fromCents($this->amount_cents));
    }

    #[Scope]
    protected function occurredBy(Builder $query, DateTimeInterface $moment): void
    {
        $query->where('occurred_at', '<=', $moment);
    }
}

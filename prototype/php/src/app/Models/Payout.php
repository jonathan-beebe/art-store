<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Money\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property-read Seller $seller
 */
#[Fillable(['seller_id', 'period_start', 'period_end', 'amount_cents', 'paid_at'])]
class Payout extends Model
{
    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Seller, $this> */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    /** @return HasMany<LedgerEntry, $this> */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function amount(): Money
    {
        return Money::fromCents($this->amount_cents);
    }
}

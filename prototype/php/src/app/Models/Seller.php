<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Escrow\LedgerBalance;
use App\Domain\Escrow\LedgerMovement;
use Database\Factories\SellerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['email', 'name', 'shop_name', 'email_verified_at'])]
#[Hidden(['remember_token'])]
class Seller extends Authenticatable
{
    /** @use HasFactory<SellerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    /** @return HasMany<Listing, $this> */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /** @return HasMany<Fulfillment, $this> */
    public function fulfillments(): HasMany
    {
        return $this->hasMany(Fulfillment::class);
    }

    /** @return HasMany<LedgerEntry, $this> */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /** @return HasMany<Payout, $this> */
    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    /** @return HasMany<Notification, $this> */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function escrowBalance(): LedgerBalance
    {
        return LedgerBalance::from(
            array_values($this->ledgerEntries->map(fn (LedgerEntry $entry): LedgerMovement => $entry->toMovement())->all()),
        );
    }

    public function displayName(): string
    {
        return $this->shop_name ?? $this->name ?? $this->email;
    }
}

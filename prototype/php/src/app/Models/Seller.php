<?php

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

    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    public function fulfillments(): HasMany
    {
        return $this->hasMany(Fulfillment::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function escrowBalance(): LedgerBalance
    {
        return LedgerBalance::from(
            $this->ledgerEntries->map(fn (LedgerEntry $entry): LedgerMovement => $entry->toMovement())->all(),
        );
    }

    public function displayName(): string
    {
        return $this->shop_name ?? $this->name ?? $this->email;
    }
}

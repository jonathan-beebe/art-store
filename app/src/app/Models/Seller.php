<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Escrow\LedgerBalance;
use App\Domain\Escrow\LedgerMovement;
use App\Models\Concerns\HasPrefixedUlid;
use Database\Factories\SellerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Override;

#[Fillable(['email', 'name', 'shop_name', 'email_verified_at'])]
#[Hidden(['remember_token'])]
class Seller extends Authenticatable
{
    /** @use HasFactory<SellerFactory> */
    use HasFactory;

    use HasPrefixedUlid;
    use Notifiable;

    public static function idPrefix(): string
    {
        return 'sel';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
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

    /** @return HasOne<StoreProfile, $this> */
    public function storeProfile(): HasOne
    {
        return $this->hasOne(StoreProfile::class);
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

    /** @return HasMany<Conversation, $this> */
    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /** @return HasMany<FulfillmentFlow, $this> */
    public function fulfillmentFlows(): HasMany
    {
        return $this->hasMany(FulfillmentFlow::class);
    }

    /**
     * The flow every listing that names none ships by.
     *
     * @return HasOne<FulfillmentFlow, $this>
     */
    public function defaultFulfillmentFlow(): HasOne
    {
        return $this->hasOne(FulfillmentFlow::class)->defaults();
    }

    /** @return MorphMany<Message, $this> */
    public function sentMessages(): MorphMany
    {
        return $this->morphMany(Message::class, 'sender');
    }

    /**
     * How many listings the seller holds in each status, counted by the
     * database.
     *
     * @return array<string, int> status value => count
     */
    public function listingCountsByStatus(): array
    {
        $counts = [];

        foreach ($this->listings()->countedByStatus()->get() as $row) {
            $counts[$row->status->value] = $row->tally;
        }

        return $counts;
    }

    public function escrowBalance(): LedgerBalance
    {
        return LedgerBalance::from(
            array_values($this->ledgerEntries()
                ->totalledByType()
                ->get()
                ->map(fn (LedgerEntry $entry): LedgerMovement => $entry->toMovement())
                ->all()),
        );
    }

    public function displayName(): string
    {
        return $this->shop_name ?? $this->name ?? $this->email;
    }

    /**
     * The full seller list, ordered the way every admin seller-filter
     * dropdown presents it.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function orderedForFilter(Builder $query): void
    {
        $query->orderBy('shop_name')->orderBy('email');
    }

    /**
     * How a thread names this account: the same name every other page shows.
     */
    public function participantName(): string
    {
        return $this->displayName();
    }
}

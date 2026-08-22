<?php

namespace App\Models;

use App\Domain\Notifications\RecipientType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['seller_id', 'customer_id', 'subject', 'body', 'url', 'read_at'])]
class Notification extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(Seller::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public static function recipientColumn(RecipientType $recipient): string
    {
        return match ($recipient) {
            RecipientType::Seller => 'seller_id',
            RecipientType::Customer => 'customer_id',
        };
    }

    #[Scope]
    protected function unread(Builder $query): void
    {
        $query->whereNull('read_at');
    }

    #[Scope]
    protected function for(Builder $query, RecipientType $recipient, int $recipientId): void
    {
        $query->where(self::recipientColumn($recipient), $recipientId);
    }
}

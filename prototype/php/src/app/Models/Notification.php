<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Notifications\NotificationMessage;
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

    public static function to(RecipientType $recipient, int $recipientId, NotificationMessage $message): self
    {
        return self::create([
            $recipient->column() => $recipientId,
            'subject' => $message->subject,
            'body' => $message->body,
            'url' => $message->url,
        ]);
    }

    /** @param Builder<$this> $query */
    #[Scope]
    protected function unread(Builder $query): void
    {
        $query->whereNull('read_at');
    }
}

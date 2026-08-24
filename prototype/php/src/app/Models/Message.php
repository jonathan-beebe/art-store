<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\ActorDisplay;
use Database\Factories\MessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Override;

/**
 * @property-read Conversation $conversation
 * @property-read Seller|Customer|Admin $sender
 */
#[Fillable(['conversation_id', 'sender_type', 'sender_id', 'body', 'sent_at', 'read_at'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Conversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** @return MorphTo<Model, $this> */
    public function sender(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Who a thread reads this message as from. Reads the relation already
     * eager-loaded rather than fetching it fresh.
     */
    public function senderName(): string
    {
        return ActorDisplay::nameOf($this->sender);
    }

    /**
     * A message is unread for a reader when nobody has read it and that
     * reader did not send it — the one definition every unread count, the
     * mark-read write, and the live badge read through.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function unreadBy(Builder $query, Seller|Customer|Admin $reader): void
    {
        $query->whereNull('read_at')
            ->where(function (Builder $sentByReader) use ($reader): void {
                $sentByReader->where('sender_type', '!=', $reader->getMorphClass())
                    ->orWhere('sender_id', '!=', $reader->id);
            });
    }

    /**
     * Everything waiting for a reader across the threads they are in — the
     * total a site's nav badge carries. `unreadBy` says what unread means;
     * this narrows it to the reader's own threads, so a message between two
     * other people never lands on their count.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function unreadInInboxOf(Builder $query, Seller|Customer|Admin $reader): void
    {
        $query->unreadBy($reader)
            ->whereHas('conversation', function (Builder $conversations) use ($reader): void {
                /** @var Builder<Conversation> $conversations */
                $conversations->withParticipant($reader);
            });
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Auth\ActorType;
use App\Models\Concerns\HasPrefixedUlid;
use App\View\ActorDisplay;
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
 * @property-read Message|null $replyTo
 */
#[Fillable(['conversation_id', 'sender_type', 'sender_id', 'reply_to_message_id', 'body', 'sent_at', 'read_at'])]
class Message extends Model
{
    /** @use HasFactory<MessageFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'msg';
    }

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
     * The message this one answers, when the reader followed a "Reply"
     * link. `nullOnDelete` on the column means a quoted message that is
     * later removed leaves this relation null. The reply message itself
     * stays.
     *
     * @return BelongsTo<Message, $this>
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }

    /**
     * Who a thread reads this message as from. Reads only the relation
     * already eager-loaded.
     */
    public function senderName(): string
    {
        return ActorDisplay::nameOf($this->sender);
    }

    /**
     * A message is unread for a reader when nobody has read it and that
     * reader did not send it — the one definition every unread count, the
     * mark-read write, and the live badge read through. The desk is every
     * admin collectively: a message any admin sent is never unread for any
     * admin, and any admin marking a thread read marks it read for the rest
     * of the desk too.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function unreadBy(Builder $query, Seller|Customer|Admin $reader): void
    {
        $query->whereNull('read_at')->where(function (Builder $notSentByReader) use ($reader): void {
            if ($reader instanceof Admin) {
                $notSentByReader->where('sender_type', '!=', ActorType::Admin->value);

                return;
            }

            $notSentByReader->where('sender_type', '!=', $reader->getMorphClass())
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

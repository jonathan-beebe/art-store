<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Auth\ActorType;
use App\Domain\Messaging\ConversationKind;
use App\Domain\Seller\DeskPresence;
use App\Domain\Seller\ReplyTime;
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Seller;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The support hub's desk: every seeded admin under one presence read from
 * configured hours, the reply-time promise, and how long the desk took to
 * answer the seller's own last question.
 */
final readonly class SupportDesk
{
    private function __construct(
        /** @var list<DeskPerson> */
        public array $people,
        public string $replyTimePromise,
        public ?ReplyTime $lastReplyTime,
    ) {}

    public static function for(Seller $seller, DateTimeImmutable $now): self
    {
        $presence = DeskPresence::of(
            $now,
            (string) config('support.hours.opens_at'),
            (string) config('support.hours.closes_at'),
        );
        $role = (string) config('support.role');

        $people = Admin::query()
            ->oldest('created_at')
            ->oldest('id')
            ->get()
            ->map(fn (Admin $admin): DeskPerson => new DeskPerson($admin->displayName(), $role, $presence->status, $presence->text))
            ->all();

        return new self(array_values($people), (string) config('support.reply_time'), self::lastReplyTime($seller));
    }

    /**
     * A configured desk fact, published for a seller to read — null while
     * it still carries its bracketed placeholder or is blank
     * (config/support.php's own comment: "a fact not known yet keeps its
     * bracketed placeholder").
     */
    public static function published(?string $value): ?string
    {
        return $value === null || $value === '' || str_starts_with($value, '[') ? null : $value;
    }

    /**
     * The gap between the seller's most recent message to the desk, across
     * every support thread, and the desk's first reply after it — null
     * while that message is still unanswered, or while the seller has never
     * written to the desk at all.
     */
    private static function lastReplyTime(Seller $seller): ?ReplyTime
    {
        $lastAsk = Message::query()
            ->whereHas(
                'conversation',
                /** @param Builder<Conversation> $conversations */
                fn (Builder $conversations): Builder => $conversations
                    ->where('kind', ConversationKind::AdminSeller)
                    ->where('seller_id', $seller->id),
            )
            ->where('sender_type', ActorType::Seller->value)
            ->orderByDesc('sent_at')
            ->first();

        if ($lastAsk === null) {
            return null;
        }

        $answer = Message::query()
            ->where('conversation_id', $lastAsk->conversation_id)
            ->where('sender_type', ActorType::Admin->value)
            ->where('sent_at', '>', $lastAsk->sent_at)
            ->oldest('sent_at')
            ->first();

        return $answer === null ? null : ReplyTime::between(
            $lastAsk->sent_at->toDateTimeImmutable(),
            $answer->sent_at->toDateTimeImmutable(),
        );
    }
}

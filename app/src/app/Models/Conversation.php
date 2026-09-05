<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Auth\ActorType;
use App\Domain\Messaging\ConversationKind;
use App\Domain\Messaging\ConversationStatus;
use App\Domain\Messaging\ConversationSubject;
use App\Domain\Messaging\FaqPrefill;
use App\Models\Concerns\HasPrefixedUlid;
use App\Support\ActorDisplay;
use Database\Factories\ConversationFactory;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Override;

/**
 * @property-read Seller|null $seller
 * @property-read Customer|null $customer
 * @property-read Admin|null $admin
 * @property-read Listing|null $listing
 * @property-read Fulfillment|null $fulfillment
 * @property-read Order|null $order
 * @property-read Message|null $latestMessage
 * @property-read int $unread_count  only after the `withUnreadCountFor` scope
 */
#[Fillable(['kind', 'title', 'subject_key', 'seller_id', 'customer_id', 'admin_id', 'listing_id', 'fulfillment_id', 'order_id', 'resolved_at', 'resolved_by_type', 'resolved_by_id', 'last_message_at'])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    use HasPrefixedUlid;

    public static function idPrefix(): string
    {
        return 'cnv';
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'kind' => ConversationKind::class,
            'resolved_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
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

    /** @return BelongsTo<Admin, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /** @return BelongsTo<Listing, $this> */
    public function listing(): BelongsTo
    {
        return $this->belongsTo(Listing::class);
    }

    /** @return BelongsTo<Fulfillment, $this> */
    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /**
     * The newest message in the thread — the preview an inbox row shows.
     *
     * @return HasOne<Message, $this>
     */
    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany('sent_at');
    }

    /** @return MorphTo<Model, $this> */
    public function resolvedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function status(): ConversationStatus
    {
        return ConversationStatus::of($this->resolved_at);
    }

    /**
     * The thread a subject names, opened if this is the first ask for it —
     * the find-or-open shape the one kind with a `subject_key` uses.
     * `subject_key` is the uniqueness spine: a second ask for the same
     * subject finds the row the first ask created rather than opening a
     * second one, even under contention.
     */
    public static function openFor(ConversationSubject $subject, DateTimeImmutable $now): self
    {
        return self::firstOrCreate(
            ['subject_key' => $subject->subjectKey()],
            [...$subject->columns(), 'last_message_at' => $now],
        );
    }

    /**
     * Moves one customer's threads onto the customer they merged into. A
     * fresh-opened thread (`subject_key` null) simply takes the new column;
     * a fulfillment thread rebuilds its key, since `subject_key` names the
     * thread's participants and the column and the key move together. Where
     * the verified customer already holds the fulfillment thread for that
     * subject, the moved one folds into it.
     */
    public static function moveCustomer(Customer $from, Customer $to): void
    {
        foreach ($from->conversations()->get() as $conversation) {
            if ($conversation->kind !== ConversationKind::Fulfillment) {
                $conversation->update(['customer_id' => $to->id]);

                continue;
            }

            $conversation->moveFulfillmentThreadTo($to);
        }
    }

    /**
     * This actor's participant id on the thread, or null when the kind holds
     * no column for that actor type — the read behind every ownership check.
     */
    public function participantIdFor(ActorType $actor): ?string
    {
        if (! $this->kind->admits($actor)) {
            return null;
        }

        /** @var string|null $id */
        $id = $this->{$actor->participantColumn()};

        return $id;
    }

    /**
     * The participant a posted message is told about: the side of the thread
     * that did not send it.
     */
    public function otherParticipant(Message $message): Seller|Customer|Admin|null
    {
        return $this->counterpart(ActorType::from($message->sender_type));
    }

    /**
     * Who a posted message is told to: the single other participant, or
     * every admin at once when the desk is the side that did not send it —
     * the desk has no one row a `read_at` or a notification could name.
     *
     * @return Collection<int, Seller|Customer|Admin>
     */
    public function recipientsOf(Message $message): Collection
    {
        if ($this->kind->isDesk() && $message->sender_type !== ActorType::Admin->value) {
            /** @var list<Seller|Customer|Admin> $admins */
            $admins = [];

            foreach (Admin::query()->get() as $admin) {
                $admins[] = $admin;
            }

            return collect($admins);
        }

        $recipient = $this->otherParticipant($message);

        /** @var Collection<int, Seller|Customer|Admin> $recipients */
        $recipients = $recipient === null ? collect() : collect([$recipient]);

        return $recipients;
    }

    /**
     * The side of the thread a viewer is not on. Reads the relation already
     * eager-loaded rather than fetching it fresh, so a caller plans its
     * eager loads up front.
     */
    public function counterpart(ActorType $viewer): Seller|Customer|Admin|null
    {
        foreach (ActorType::cases() as $actorType) {
            if ($actorType === $viewer) {
                continue;
            }

            if ($this->participantIdFor($actorType) === null) {
                continue;
            }

            return match ($actorType) {
                ActorType::Seller => $this->seller,
                ActorType::Customer => $this->customer,
                ActorType::Admin => $this->admin,
            };
        }

        return null;
    }

    /**
     * How a viewer's inbox names the other side of the thread. A seller or a
     * customer on a desk thread sees the desk itself, whether or not an
     * admin has answered yet; an admin on an oversight thread (seller ↔
     * customer, neither of them the desk) sees both sides at once, since an
     * admin is not a participant there to have a single counterpart.
     */
    public function counterpartName(ActorType $viewer): string
    {
        if ($this->kind->isDesk() && $viewer !== ActorType::Admin) {
            return ActorDisplay::SUPPORT_DESK;
        }

        if (! $this->kind->isDesk() && $viewer === ActorType::Admin) {
            return ActorDisplay::nameOf($this->seller).' ↔ '.ActorDisplay::nameOf($this->customer);
        }

        return ActorDisplay::nameOf($this->counterpart($viewer));
    }

    /**
     * What a listing-question thread offers to carry onto a published FAQ
     * entry, or null for a thread with no listing to publish one against.
     * The opening message reads as the question and the seller's latest
     * reply as the answer, so a thread the seller has not answered yet
     * offers nothing to publish. Reads the `messages` relation already
     * eager-loaded rather than fetching it fresh.
     */
    public function faqPrefill(): ?FaqPrefill
    {
        if ($this->kind !== ConversationKind::ListingQuestion) {
            return null;
        }

        $messages = $this->messages->sortBy([['sent_at', 'asc'], ['id', 'asc']]);
        $question = $messages->first();
        $answer = $messages->where('sender_type', ActorType::Seller->value)->last();

        return $question !== null && $answer !== null
            ? FaqPrefill::of($question->body, $answer->body, $answer->id)
            : null;
    }

    /**
     * The threads a given actor is a participant in — the one query the
     * inbox and the unread-count total both filter through. The desk is
     * every admin collectively, so an admin's inbox is the two support
     * kinds rather than a column match on `admin_id`, which only ever names
     * who first answered.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function withParticipant(Builder $query, Seller|Customer|Admin $actor): void
    {
        if ($actor instanceof Admin) {
            $query->whereIn('kind', [ConversationKind::AdminSeller, ConversationKind::AdminCustomer]);

            return;
        }

        $actorType = ActorType::from($actor->getMorphClass());

        $query->where($actorType->participantColumn(), $actor->id);
    }

    /**
     * The seller ↔ customer threads an admin may read but never posts on —
     * listed separately from `withParticipant`, since nobody on the desk is
     * waited on there and they never count toward the admin's badge.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function forOversight(Builder $query): void
    {
        $query->whereIn('kind', [ConversationKind::Fulfillment, ConversationKind::ListingQuestion]);
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function withStatus(Builder $query, ConversationStatus $status): void
    {
        $status === ConversationStatus::Open ? $query->whereNull('resolved_at') : $query->whereNotNull('resolved_at');
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function ofKind(Builder $query, ConversationKind ...$kinds): void
    {
        $query->whereIn('kind', $kinds);
    }

    /**
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function unreadOnly(Builder $query, Seller|Customer|Admin $reader): void
    {
        $query->whereHas('messages', function (Builder $messages) use ($reader): void {
            /** @var Builder<Message> $messages */
            $messages->unreadBy($reader);
        });
    }

    /**
     * The per-thread unread badge an inbox row carries, counted in SQL for
     * the whole page rather than per row.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function withUnreadCountFor(Builder $query, Seller|Customer|Admin $reader): void
    {
        $query->withCount(['messages as unread_count' => function (Builder $messages) use ($reader): void {
            /** @var Builder<Message> $messages */
            $messages->unreadBy($reader);
        }]);
    }

    /**
     * Rebuilds this fulfillment thread's key for its new customer, and folds
     * it into the thread the verified customer already holds for that
     * subject when there is one.
     */
    private function moveFulfillmentThreadTo(Customer $to): void
    {
        $subjectKey = ConversationSubject::for($this->kind, [
            'seller_id' => $this->seller_id,
            'customer_id' => $to->id,
            'fulfillment_id' => $this->fulfillment_id,
        ])->subjectKey();

        $existing = self::query()->where('subject_key', $subjectKey)->first();

        if ($existing === null) {
            $this->update(['customer_id' => $to->id, 'subject_key' => $subjectKey]);

            return;
        }

        $existing->absorb($this);
    }

    /**
     * Takes over another thread's messages and drops it — how two threads
     * that turn out to name one subject become one. `last_message_at` is the
     * newest message's instant, so it is read back rather than carried over.
     */
    private function absorb(self $other): void
    {
        $other->messages()->update(['conversation_id' => $this->id]);
        $other->delete();

        $newest = $this->messages()->max('sent_at');

        if ($newest !== null) {
            $this->update(['last_message_at' => $newest]);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Auth\ActorType;
use App\Domain\Messaging\ConversationKind;
use App\Domain\Messaging\ConversationSubject;
use App\Domain\Messaging\FaqPrefill;
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
use Override;

/**
 * @property-read Seller|null $seller
 * @property-read Customer|null $customer
 * @property-read Admin|null $admin
 * @property-read Listing|null $listing
 * @property-read Fulfillment|null $fulfillment
 * @property-read Message|null $latestMessage
 */
#[Fillable(['kind', 'subject_key', 'seller_id', 'customer_id', 'admin_id', 'listing_id', 'fulfillment_id', 'last_message_at'])]
class Conversation extends Model
{
    /** @use HasFactory<ConversationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'kind' => ConversationKind::class,
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
        return $this->hasOne(Message::class)->latestOfMany('id');
    }

    /**
     * The thread a subject names, opened if this is the first ask for it.
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
     * Moves one customer's threads onto the customer they merged into.
     * `subject_key` names the thread's participants, so the column and the
     * key move together: a thread left holding the merged customer's key is
     * found by no later ask for its subject, and the next one opens a second
     * thread beside it. Where the verified customer already holds the thread
     * for a subject, the moved one folds into it.
     */
    public static function moveCustomer(Customer $from, Customer $to): void
    {
        foreach ($from->conversations()->get() as $conversation) {
            $subjectKey = ConversationSubject::for(
                $conversation->kind,
                ['customer_id' => $to->id] + $conversation->idColumns(),
            )->subjectKey();

            $existing = self::query()->where('subject_key', $subjectKey)->first();

            if ($existing === null) {
                $conversation->update(['customer_id' => $to->id, 'subject_key' => $subjectKey]);

                continue;
            }

            $existing->absorb($conversation);
        }
    }

    /**
     * This actor's participant id on the thread, or null when the kind holds
     * no column for that actor type — the read behind every ownership check.
     */
    public function participantIdFor(ActorType $actor): ?int
    {
        if (! $this->kind->admits($actor)) {
            return null;
        }

        /** @var int|null $id */
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
     * How a viewer's inbox names the other side of the thread.
     */
    public function counterpartName(ActorType $viewer): string
    {
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

        $messages = $this->messages->sortBy('id');
        $question = $messages->first();
        $answer = $messages->where('sender_type', ActorType::Seller->value)->last();

        return $question !== null && $answer !== null
            ? FaqPrefill::of($question->body, $answer->body, $answer->id)
            : null;
    }

    /**
     * The id columns a subject reads, keyed the way it names them.
     *
     * @return array<string, int|null>
     */
    private function idColumns(): array
    {
        return [
            'seller_id' => $this->seller_id,
            'customer_id' => $this->customer_id,
            'admin_id' => $this->admin_id,
            'listing_id' => $this->listing_id,
            'fulfillment_id' => $this->fulfillment_id,
        ];
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

    /**
     * The threads a given actor is a participant in — the one query the
     * inbox and the unread-count total both filter through.
     *
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function withParticipant(Builder $query, Seller|Customer|Admin $actor): void
    {
        $actorType = ActorType::from($actor->getMorphClass());

        $query->where($actorType->participantColumn(), $actor->id);
    }
}

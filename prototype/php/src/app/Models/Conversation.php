<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Auth\ActorType;
use App\Domain\Messaging\ConversationKind;
use App\Domain\Messaging\ConversationSubject;
use Database\Factories\ConversationFactory;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Override;

/**
 * @property-read Seller|null $seller
 * @property-read Customer|null $customer
 * @property-read Admin|null $admin
 * @property-read Listing|null $listing
 * @property-read Fulfillment|null $fulfillment
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
     * that did not send it. Reads the relation already eager-loaded rather
     * than fetching it fresh, so a caller plans its eager loads up front.
     */
    public function otherParticipant(Message $message): Seller|Customer|Admin|null
    {
        foreach (ActorType::cases() as $actorType) {
            if ($actorType->value === $message->sender_type) {
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

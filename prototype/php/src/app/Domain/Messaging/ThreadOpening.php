<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

/**
 * What a fresh thread is opened with: its kind, its known side(s), a title,
 * and — for the two kinds that carry one — the context row it was raised
 * over. Unlike `ConversationSubject`, this carries no `subject_key`: the
 * three kinds it opens for (`OpenThread`) never find an existing thread, so
 * every ask writes a new row. A support kind's other side is the desk
 * itself, not one admin, so no admin id is named here — `PostMessage` stamps
 * `admin_id` on the first admin reply.
 */
final readonly class ThreadOpening
{
    /**
     * @param  array<string, string>  $participantIds  the known participant column(s), by column name
     * @param  array{column: string, id: string}|null  $context  the context column and id this opening carries, for the kinds that have one
     */
    private function __construct(
        public ConversationKind $kind,
        private array $participantIds,
        public ThreadTitle $title,
        private ?array $context,
    ) {}

    public static function adminSeller(string $sellerId, ThreadTitle $title, ?string $fulfillmentId = null): self
    {
        return new self(
            ConversationKind::AdminSeller,
            ['seller_id' => $sellerId],
            $title,
            self::context(ConversationKind::AdminSeller, $fulfillmentId),
        );
    }

    public static function adminCustomer(string $customerId, ThreadTitle $title, ?string $orderId = null): self
    {
        return new self(
            ConversationKind::AdminCustomer,
            ['customer_id' => $customerId],
            $title,
            self::context(ConversationKind::AdminCustomer, $orderId),
        );
    }

    public static function listingQuestion(string $sellerId, string $customerId, string $listingId, ThreadTitle $title): self
    {
        return new self(
            ConversationKind::ListingQuestion,
            ['seller_id' => $sellerId, 'customer_id' => $customerId],
            $title,
            self::context(ConversationKind::ListingQuestion, $listingId),
        );
    }

    /**
     * @return array{column: string, id: string}|null
     */
    private static function context(ConversationKind $kind, ?string $id): ?array
    {
        return $id === null ? null : ['column' => $kind->contextColumns()[0], 'id' => $id];
    }

    /**
     * The `conversations` columns a fresh row opens with.
     *
     * @return array<string, string>
     */
    public function columns(): array
    {
        $columns = ['kind' => $this->kind->value, 'title' => $this->title->value, ...$this->participantIds];

        if ($this->context !== null) {
            $columns[$this->context['column']] = $this->context['id'];
        }

        return $columns;
    }
}

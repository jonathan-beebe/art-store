<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use InvalidArgumentException;

/**
 * What a fulfillment thread is about: the seller, the customer, and the
 * order it is on. `subjectKey()` folds this into the one string
 * `conversations_subject_key_unique` guards. Asking for the same
 * fulfillment's thread twice finds the row the first ask opened.
 * Fulfillment is the one kind that finds an existing thread. The other
 * three kinds build a `ThreadOpening`.
 */
final readonly class ConversationSubject
{
    private function __construct(
        public ConversationKind $kind,
        private string $sellerId,
        private string $customerId,
        private string $fulfillmentId,
    ) {}

    public static function fulfillment(string $sellerId, string $customerId, string $fulfillmentId): self
    {
        return new self(ConversationKind::Fulfillment, $sellerId, $customerId, $fulfillmentId);
    }

    /**
     * The subject a fulfillment row already names, rebuilt from its id
     * columns — what a row whose participant id moved reads to recompute its
     * key.
     *
     * @param  array<string, string|null>  $ids  the row's id columns, keyed by column name
     */
    public static function for(ConversationKind $kind, array $ids): self
    {
        if ($kind !== ConversationKind::Fulfillment) {
            throw new InvalidArgumentException('Only a fulfillment conversation carries a subject_key to rebuild.');
        }

        return self::fulfillment(
            self::id($kind, $ids, 'seller_id'),
            self::id($kind, $ids, 'customer_id'),
            self::id($kind, $ids, 'fulfillment_id'),
        );
    }

    /**
     * `fulfillment:s<seller>:c<customer>:f<fulfillment>` — one non-null
     * string with no hole a unique index can slip through.
     */
    public function subjectKey(): string
    {
        return "fulfillment:s{$this->sellerId}:c{$this->customerId}:f{$this->fulfillmentId}";
    }

    /**
     * The `conversations` columns this subject writes: the kind and its
     * three id columns.
     *
     * @return array<string, string>
     */
    public function columns(): array
    {
        return [
            'kind' => $this->kind->value,
            'seller_id' => $this->sellerId,
            'customer_id' => $this->customerId,
            'fulfillment_id' => $this->fulfillmentId,
        ];
    }

    /**
     * @param  array<string, string|null>  $ids
     */
    private static function id(ConversationKind $kind, array $ids, string $column): string
    {
        return $ids[$column] ?? throw new InvalidArgumentException("A {$kind->value} conversation names a {$column}.");
    }
}

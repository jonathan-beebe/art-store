<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use InvalidArgumentException;

/**
 * What a thread is about: the kind, its two participants, and — for the two
 * kinds that carry one — the row it is a subject of. `subjectKey()` folds
 * this into the one string `conversations_subject_key_unique` guards, so
 * asking for the same subject twice finds the row the first ask opened
 * rather than a second one.
 */
final readonly class ConversationSubject
{
    /** @var array<string, string> conversations column => the short letter subjectKey() uses */
    private const COLUMN_LETTERS = [
        'admin_id' => 'a',
        'seller_id' => 's',
        'customer_id' => 'c',
        'listing_id' => 'l',
        'fulfillment_id' => 'f',
    ];

    /**
     * @param  array<string, string>  $participantIds  the two participant columns, in the order the key reads them
     * @param  array{column: string, id: string}|null  $subject  the column and id of the row this is a subject of, for the two kinds that carry one
     */
    private function __construct(
        public ConversationKind $kind,
        private array $participantIds,
        private ?array $subject,
    ) {}

    public static function adminSeller(string $adminId, string $sellerId): self
    {
        return new self(ConversationKind::AdminSeller, ['admin_id' => $adminId, 'seller_id' => $sellerId], null);
    }

    public static function adminCustomer(string $adminId, string $customerId): self
    {
        return new self(ConversationKind::AdminCustomer, ['admin_id' => $adminId, 'customer_id' => $customerId], null);
    }

    public static function fulfillment(string $sellerId, string $customerId, string $fulfillmentId): self
    {
        return new self(
            ConversationKind::Fulfillment,
            ['seller_id' => $sellerId, 'customer_id' => $customerId],
            ['column' => 'fulfillment_id', 'id' => $fulfillmentId],
        );
    }

    public static function listingQuestion(string $sellerId, string $customerId, string $listingId): self
    {
        return new self(
            ConversationKind::ListingQuestion,
            ['seller_id' => $sellerId, 'customer_id' => $customerId],
            ['column' => 'listing_id', 'id' => $listingId],
        );
    }

    /**
     * The subject a row already names, rebuilt from its kind and its id
     * columns — what a row whose participant ids moved reads to recompute
     * its key.
     *
     * @param  array<string, string|null>  $ids  the row's id columns, keyed by column name
     */
    public static function for(ConversationKind $kind, array $ids): self
    {
        $participantIds = [];

        foreach ($kind->participantColumns() as $column) {
            $participantIds[$column] = self::id($kind, $ids, $column);
        }

        $subjectColumn = $kind->subjectColumn();

        return new self(
            $kind,
            $participantIds,
            $subjectColumn === null
                ? null
                : ['column' => $subjectColumn, 'id' => self::id($kind, $ids, $subjectColumn)],
        );
    }

    /**
     * `kind:<letter><id>:<letter><id>[:<letter><id>]` — one non-null string
     * with no hole a unique index can slip through.
     */
    public function subjectKey(): string
    {
        $parts = [$this->kind->value];

        foreach ($this->participantIds as $column => $id) {
            $parts[] = self::COLUMN_LETTERS[$column].$id;
        }

        if ($this->subject !== null) {
            $parts[] = self::COLUMN_LETTERS[$this->subject['column']].$this->subject['id'];
        }

        return implode(':', $parts);
    }

    /**
     * The `conversations` columns this subject writes: the kind, its two
     * participants, and — for the two kinds that carry one — the subject row.
     *
     * @return array<string, string>
     */
    public function columns(): array
    {
        $columns = ['kind' => $this->kind->value, ...$this->participantIds];

        if ($this->subject !== null) {
            $columns[$this->subject['column']] = $this->subject['id'];
        }

        return $columns;
    }

    /**
     * @param  array<string, string|null>  $ids
     */
    private static function id(ConversationKind $kind, array $ids, string $column): string
    {
        return $ids[$column] ?? throw new InvalidArgumentException("A {$kind->value} conversation names a {$column}.");
    }
}

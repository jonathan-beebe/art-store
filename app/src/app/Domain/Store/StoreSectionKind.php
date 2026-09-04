<?php

declare(strict_types=1);

namespace App\Domain\Store;

/**
 * What a store page is built from. A page grows by adding a case here, a
 * renderer, and — when the kind needs columns no other kind has — a child
 * table keyed by section. The profile row stays the identity of the store;
 * everything the page says is a section of one of these kinds.
 *
 * {@see allows()} is the one statement of which fields a kind uses. The
 * form request reads it, so a heading on a kind that has none, or a body on
 * a gallery, is refused before a row is written.
 */
enum StoreSectionKind: string
{
    case Story = 'story';
    case Gallery = 'gallery';

    public function label(): string
    {
        return match ($this) {
            self::Story => 'Story',
            self::Gallery => 'Gallery',
        };
    }

    /** The sentence the seller's Add-section control reads under the label. */
    public function description(): string
    {
        return match ($this) {
            self::Story => 'Who you are, how you work, why you make what you make.',
            self::Gallery => 'A row of your pictures, in an order you choose.',
        };
    }

    /**
     * @return list<StoreSectionField>
     */
    public function fields(): array
    {
        return match ($this) {
            self::Story => [StoreSectionField::Heading, StoreSectionField::Body],
            self::Gallery => [StoreSectionField::Heading, StoreSectionField::Images],
        };
    }

    public function allows(StoreSectionField $field): bool
    {
        return in_array($field, $this->fields(), true);
    }
}

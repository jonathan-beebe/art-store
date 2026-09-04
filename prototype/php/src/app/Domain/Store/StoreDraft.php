<?php

declare(strict_types=1);

namespace App\Domain\Store;

/**
 * What a seller typed on the Store screen: the identity of the store and
 * the places it points to, carried from the form request to the action as
 * one value.
 */
final readonly class StoreDraft
{
    /**
     * @param  array<string, string>  $links  {@see StoreLinkKind} value => the address the seller typed
     */
    private function __construct(
        public string $name,
        public string $slug,
        public ?string $tagline,
        public ?string $location,
        public StoreVisibility $visibility,
        public array $links,
    ) {}

    /**
     * @param  array<string, string>  $links
     */
    public static function of(
        string $name,
        string $slug,
        ?string $tagline,
        ?string $location,
        StoreVisibility $visibility,
        array $links = [],
    ): self {
        return new self($name, $slug, $tagline, $location, $visibility, $links);
    }

    /**
     * The columns the profile row takes, apart from the address and the
     * published stamp — the two the action writes through their own paths.
     *
     * @return array<string, string|null>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'tagline' => $this->tagline,
            'location' => $this->location,
        ];
    }

    /**
     * The links in the order {@see StoreLinkKind} declares them, each with
     * the position its row carries.
     *
     * @return list<array{kind: StoreLinkKind, url: string, position: int}>
     */
    public function orderedLinks(): array
    {
        $ordered = [];

        foreach (StoreLinkKind::cases() as $index => $kind) {
            if (isset($this->links[$kind->value])) {
                $ordered[] = ['kind' => $kind, 'url' => $this->links[$kind->value], 'position' => $index];
            }
        }

        return $ordered;
    }
}

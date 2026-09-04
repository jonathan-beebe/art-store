<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Seller\ActivityKind;

/**
 * A feed's `?kind=` as the page renders it: the kind in force, and one link
 * per kind beside the All link that carries none. The reader fetches every
 * source whatever the filter says and the pure feed narrows what it hands
 * back, so a filtered page never disagrees with an unfiltered one.
 */
final readonly class FeedFilter
{
    /**
     * @param  list<FeedKindLink>  $links
     */
    private function __construct(
        public ?ActivityKind $kind,
        public array $links,
    ) {}

    /**
     * @param  array<string, string>  $roundTripped  what the page carries beside `kind`
     */
    public static function build(string $routeName, array $roundTripped, ?ActivityKind $kind): self
    {
        $links = [new FeedKindLink(
            label: 'All',
            href: route($routeName, $roundTripped),
            active: $kind === null,
        )];

        foreach (ActivityKind::cases() as $case) {
            $links[] = new FeedKindLink(
                label: $case->label(),
                href: route($routeName, [...$roundTripped, 'kind' => $case->value]),
                active: $kind === $case,
            );
        }

        return new self($kind, $links);
    }
}

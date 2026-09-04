<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * One focus group of the dashboard's "Needs your attention" row: a
 * heading that counts what is waiting, a sentence saying what the group
 * holds, the link to the tool that clears it, and the rows themselves.
 * A group with no rows says so in `$emptySentence` — the page never
 * renders a blank panel.
 */
final readonly class AttentionGroup
{
    /**
     * @param  list<AttentionRow>  $rows
     */
    public function __construct(
        public FeedIcon $icon,
        public string $title,
        public string $supporting,
        public string $actionLabel,
        public string $actionHref,
        public array $rows,
        public string $emptySentence,
    ) {}

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }
}

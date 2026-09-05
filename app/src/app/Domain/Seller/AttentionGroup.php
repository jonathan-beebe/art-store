<?php

declare(strict_types=1);

namespace App\Domain\Seller;

/**
 * One focus group of the dashboard's "Needs your attention" row: a
 * heading that counts what is waiting, a sentence saying what the group
 * holds, the tool that clears it, and the rows themselves. `$total` is
 * the whole queue; `$rows` is the head of it the panel shows. A group
 * with nothing waiting says so in `$emptySentence` — the page never
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
        public AttentionTool $tool,
        public array $rows,
        public int $total,
        public string $emptySentence,
    ) {}

    public function isEmpty(): bool
    {
        return $this->rows === [];
    }

    /** How many the queue holds beyond the rows on the panel. */
    public function hidden(): int
    {
        return max(0, $this->total - count($this->rows));
    }
}

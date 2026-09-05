<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Domain\Analytics\JumpKind;

/**
 * A search string that names exactly one listing or actor —
 * {@see AnalyticsJump::for()}'s result, what the entry page's jump row
 * renders and links to.
 */
final readonly class Jump
{
    public function __construct(
        public string $id,
        public string $caption,
        public JumpKind $kind,
    ) {}
}

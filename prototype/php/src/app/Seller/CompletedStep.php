<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Fulfillment\FlowStep;
use DateTimeImmutable;

/**
 * A step the log says is behind this parcel: which step, who marked it, and
 * when. The steps panel reads one of these per completed step, so a seller
 * sees who did the work as well as that it was done.
 */
final readonly class CompletedStep
{
    public function __construct(
        public FlowStep $step,
        public string $actor,
        public DateTimeImmutable $occurredAt,
    ) {}

    public function line(): string
    {
        return "Done by {$this->actor} · ".$this->occurredAt->format('M j');
    }
}

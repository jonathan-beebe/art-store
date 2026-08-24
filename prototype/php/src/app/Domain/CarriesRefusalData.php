<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * A `DomainRuleViolation` that carries more than its message: facts about the
 * refusal `Story::tell()` folds into the `refused` log line's `data`,
 * alongside whatever the unit of work already knew about itself
 * (docs/alignment.md §2.3). A violation that has nothing more to say than its
 * message does not implement this.
 */
interface CarriesRefusalData
{
    /**
     * @return array<string, mixed>
     */
    public function refusalData(): array;
}

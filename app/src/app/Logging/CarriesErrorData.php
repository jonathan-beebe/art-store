<?php

declare(strict_types=1);

namespace App\Logging;

/**
 * A `Throwable` that carries more than its message: the sub-category within
 * its type, and the facts about what it was doing, that `Story::failed()`'s
 * `error` object folds in beside `type`/`message`/`stack`
 * (docs/spec.md §2.1) when it carries them. An exception that has
 * nothing more to say than its message does not implement this.
 */
interface CarriesErrorData
{
    public function errorReason(): ?string;

    /**
     * @return array<string, mixed>
     */
    public function errorData(): array;
}

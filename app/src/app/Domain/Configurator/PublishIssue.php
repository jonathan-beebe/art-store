<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

/**
 * One reason a listing's configurator state is not ready to publish, named
 * so a seller-facing page can list every issue in one pass. `subjectId`
 * names the row the issue is about (a variant's id, for example) when
 * there is one, so the seller-facing screen can link straight to it
 * instead of the general screen that owns its kind of issue.
 */
final readonly class PublishIssue
{
    private function __construct(public string $code, public string $message, public ?string $subjectId = null) {}

    public static function of(string $code, string $message, ?string $subjectId = null): self
    {
        return new self($code, $message, $subjectId);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

/**
 * One reason a listing's configurator state is not ready to publish, named so
 * a seller-facing page can list every issue rather than stopping at the
 * first.
 */
final readonly class PublishIssue
{
    private function __construct(public string $code, public string $message) {}

    public static function of(string $code, string $message): self
    {
        return new self($code, $message);
    }
}

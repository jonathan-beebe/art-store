<?php

declare(strict_types=1);

namespace App\Configurator;

/**
 * One {@see \App\Domain\Configurator\PublishIssue} translated into what the
 * seller-facing publish panel renders: a plain-language sentence naming the
 * problem, and the label and URL of the link that takes the seller to its fix.
 */
final readonly class PresentedPublishIssue
{
    private function __construct(public string $message, public string $fixLabel, public string $fixUrl) {}

    public static function of(string $message, string $fixLabel, string $fixUrl): self
    {
        return new self($message, $fixLabel, $fixUrl);
    }
}

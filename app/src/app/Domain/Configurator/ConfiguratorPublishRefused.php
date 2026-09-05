<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

use App\Domain\CarriesRefusalData;
use App\Domain\DomainRuleViolation;

/**
 * The full list of reasons a listing's configurator state is not ready to
 * publish, carried together. `getMessage()` reads as one sentence.
 * `refusalData()` carries every issue into the `refused` log line's `data`
 * (docs/spec.md §2.3).
 */
final class ConfiguratorPublishRefused extends DomainRuleViolation implements CarriesRefusalData
{
    /**
     * @param  list<PublishIssue>  $issues
     */
    public function __construct(public readonly array $issues)
    {
        parent::__construct(self::messageFor($issues));
    }

    /**
     * @param  list<PublishIssue>  $issues
     */
    public static function ifAny(array $issues): void
    {
        if ($issues !== []) {
            throw new self($issues);
        }
    }

    /**
     * @return array{issues: list<array{code: string, message: string}>}
     */
    public function refusalData(): array
    {
        return [
            'issues' => array_map(
                fn (PublishIssue $issue): array => ['code' => $issue->code, 'message' => $issue->message],
                $this->issues,
            ),
        ];
    }

    /**
     * @param  list<PublishIssue>  $issues
     */
    private static function messageFor(array $issues): string
    {
        $count = count($issues);

        return $count === 1
            ? "This listing is not ready to publish: {$issues[0]->message}"
            : "This listing is not ready to publish: {$count} issues found.";
    }
}

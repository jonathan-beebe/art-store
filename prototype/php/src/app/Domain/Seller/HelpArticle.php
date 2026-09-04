<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use InvalidArgumentException;

/**
 * One help article, parsed from a markdown file's front matter and body.
 * The front matter is `key: value` lines between two `---` markers; the
 * body is plain paragraphs, blank-line separated — the minimal markdown
 * subset four short articles need, with no library behind it.
 */
final readonly class HelpArticle
{
    private function __construct(
        public string $slug,
        public string $group,
        public string $title,
        public int $position,
        /** @var list<string> */
        public array $paragraphs,
    ) {}

    public static function fromMarkdown(string $raw): self
    {
        [$frontMatter, $body] = self::split($raw);
        $fields = self::parseFrontMatter($frontMatter);

        return new self(
            slug: self::required($fields, 'slug'),
            group: self::required($fields, 'group'),
            title: self::required($fields, 'title'),
            position: isset($fields['position']) ? (int) $fields['position'] : 0,
            paragraphs: self::paragraphs($body),
        );
    }

    /**
     * @return array{0: string, 1: string} the front matter block and the body, both untrimmed
     */
    private static function split(string $raw): array
    {
        $trimmed = trim($raw);

        if (! str_starts_with($trimmed, '---')) {
            throw new InvalidArgumentException('A help article starts with a --- front matter block.');
        }

        $parts = preg_split('/^---\s*$/m', $trimmed, 3);

        if (! is_array($parts) || count($parts) < 3) {
            throw new InvalidArgumentException('A help article\'s front matter is not closed with a second ---.');
        }

        return [$parts[1], $parts[2]];
    }

    /**
     * @return array<string, string>
     */
    private static function parseFrontMatter(string $block): array
    {
        $fields = [];

        foreach (preg_split('/\r?\n/', trim($block)) ?: [] as $line) {
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }

            [$key, $value] = explode(':', $line, 2);
            $fields[trim($key)] = trim($value);
        }

        return $fields;
    }

    /**
     * @param  array<string, string>  $fields
     */
    private static function required(array $fields, string $key): string
    {
        return $fields[$key] ?? throw new InvalidArgumentException("A help article's front matter is missing \"{$key}\".");
    }

    /**
     * @return list<string>
     */
    private static function paragraphs(string $body): array
    {
        $blocks = preg_split('/\n{2,}/', trim($body)) ?: [];

        return array_values(array_filter(
            array_map(fn (string $block): string => trim(preg_replace('/\s+/', ' ', $block) ?? $block), $blocks),
            fn (string $block): bool => $block !== '',
        ));
    }
}

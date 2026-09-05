<?php

declare(strict_types=1);

namespace App\Domain\Identifiers;

use App\Domain\DomainRuleViolation;
use Stringable;

/**
 * A row's public identifier: a three-letter table prefix, an underscore, and
 * the 26-character body of a ULID in uppercase Crockford base32. Thirty
 * characters in all, and the same string wherever the row is referred to —
 * in a URL, in a foreign key, in a log line.
 *
 * The prefix is what makes the id self-describing, so a value carrying
 * another table's prefix is not this table's id at all. `parse()` is the one
 * place that judgement is made; a route boundary reads it before it looks
 * for a row.
 */
final readonly class PrefixedId implements Stringable
{
    public const int PREFIX_LENGTH = 3;

    public const int ULID_LENGTH = 26;

    public const int LENGTH = 30;

    private const string SEPARATOR = '_';

    private const string PREFIX_PATTERN = '/^[a-z]{3}$/';

    /**
     * Crockford base32 drops I, L, O, and U, and a ULID's first character
     * carries the top bits of the millisecond, which cannot exceed 7.
     */
    private const string ULID_PATTERN = '/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/';

    private function __construct(public string $prefix, public string $ulid) {}

    /**
     * The id a prefix and a ULID body spell together.
     *
     * @throws DomainRuleViolation when either half is not the shape an id holds
     */
    public static function of(string $prefix, string $ulid): self
    {
        if (preg_match(self::PREFIX_PATTERN, $prefix) !== 1) {
            throw new DomainRuleViolation("An id prefix is three lowercase letters, got \"{$prefix}\".");
        }

        if (preg_match(self::ULID_PATTERN, $ulid) !== 1) {
            throw new DomainRuleViolation("An id body is a 26-character uppercase ULID, got \"{$ulid}\".");
        }

        return new self($prefix, $ulid);
    }

    /**
     * Reads `<prefix>_<ulid>` and refuses everything else: another table's
     * prefix, no prefix at all, a bare ULID, the wrong length, a lowercase
     * body. Null means the value names no row of that table.
     */
    public static function parse(string $prefix, string $value): ?self
    {
        if (strlen($value) !== self::LENGTH) {
            return null;
        }

        if (! str_starts_with($value, $prefix.self::SEPARATOR)) {
            return null;
        }

        $ulid = substr($value, self::PREFIX_LENGTH + 1);

        return preg_match(self::PREFIX_PATTERN, $prefix) === 1
            && preg_match(self::ULID_PATTERN, $ulid) === 1
            ? new self($prefix, $ulid)
            : null;
    }

    public function __toString(): string
    {
        return $this->prefix.self::SEPARATOR.$this->ulid;
    }
}

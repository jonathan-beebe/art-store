<?php

declare(strict_types=1);

namespace App\Domain\Logging;

/**
 * docs/spec.md §2.1's redaction rule — no cookie values, magic-link
 * tokens, card numbers, or email addresses in `data` — applied to a value the
 * app does not otherwise control the shape of. A field the story writes for
 * itself (`order_id`, `amount_cents`, …) already leaves those out by
 * construction; the one boundary that needs a filter is the request's own
 * query string (§2.2's `data.query`), where a key and a value can be
 * whatever a visitor typed — an admin's `/admin/logs?value=` search included.
 *
 * A cookie's own value has no shape distinct from any other opaque string, so
 * nothing here matches it specifically; a cookie never reaches the query
 * string in this application in the first place.
 */
final class DataRedaction
{
    private function __construct() {} // @codeCoverageIgnore

    private const string REDACTED = '[redacted]';

    private const string EMAIL = '/^[^@\s]+@[^@\s]+\.[^@\s]+$/';

    /** A magic-link token: forty random bytes, hex-encoded
     * (`App\Actions\Auth\SendMagicLink::TOKEN_BYTES`). */
    private const string TOKEN = '/^[a-f0-9]{80}$/';

    /** A card number's digit count across the networks the fake card table
     * exercises, spaces and dashes allowed as a person might type them. */
    private const string CARD_NUMBER = '/^\d{13,19}$/';

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public static function redact(array $data): array
    {
        return array_map(self::value(...), $data);
    }

    private static function value(mixed $value): mixed
    {
        if (is_array($value)) {
            return self::redact($value);
        }

        return is_string($value) && self::looksSensitive($value) ? self::REDACTED : $value;
    }

    private static function looksSensitive(string $value): bool
    {
        return preg_match(self::EMAIL, $value) === 1
            || preg_match(self::TOKEN, $value) === 1
            || preg_match(self::CARD_NUMBER, str_replace([' ', '-'], '', $value)) === 1;
    }
}

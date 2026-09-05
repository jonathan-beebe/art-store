<?php

declare(strict_types=1);

namespace App\Logging\Admin;

use App\Domain\Identifiers\PrefixedId;
use App\Logging\LogDomain;
use App\Logging\StoryEvent;
use App\Logging\StoryLevel;
use App\Logging\StoryPhase;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The one vocabulary of log filters (docs/spec.md §5, `/admin/logs`),
 * shared by the admin viewer's query string (`LogsQueryRequest`) and the
 * MCP `search-logs` and `tally-logs` tools: the validation rule per
 * field, the one cross-field rule, and the `LogRowFilters` a validated
 * set builds.
 */
final class LogFilterInput
{
    public const array FIELDS = ['domain', 'level', 'phase', 'event', 'request', 'txn', 'session', 'actor', 'msg', 'from', 'to', 'key', 'value'];

    public const string REQUEST_ID_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

    public const string ISO_INSTANT_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/';

    public const array ACTOR_PREFIXES = ['adm', 'sel', 'cus'];

    public const string VALUE_NEEDS_KEY = 'a value filter needs a key';

    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'domain' => ['nullable', Rule::enum(LogDomain::class)],
            'level' => ['nullable', Rule::enum(StoryLevel::class)],
            'phase' => ['nullable', Rule::enum(StoryPhase::class)],
            'event' => ['nullable', Rule::enum(StoryEvent::class)],
            'request' => ['nullable', 'string', 'regex:'.self::REQUEST_ID_PATTERN],
            'txn' => ['nullable', self::prefixedIdRule(['txn'])],
            'session' => ['nullable', self::prefixedIdRule(['ses'])],
            'actor' => ['nullable', self::prefixedIdRule(self::ACTOR_PREFIXES)],
            'msg' => ['nullable', 'string'],
            'from' => ['nullable', 'string', 'regex:'.self::ISO_INSTANT_PATTERN],
            'to' => ['nullable', 'string', 'regex:'.self::ISO_INSTANT_PATTERN],
            'key' => ['nullable', 'string', 'regex:'.LogRowFilters::ATTRIBUTE_KEY_PATTERN],
            'value' => ['nullable', 'string'],
        ];
    }

    /**
     * An empty string means the field was left blank: a cleared form field,
     * or an agent passing `""` for "no filter".
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function blanked(array $input): array
    {
        return array_map(fn (mixed $value): mixed => $value === '' ? null : $value, $input);
    }

    /**
     * The one cross-field rule: `value` narrows `key`, so it needs one.
     *
     * @param  array<string, mixed>  $input
     */
    public static function valueLacksKey(array $input): bool
    {
        return ($input['value'] ?? null) !== null && ($input['key'] ?? null) === null;
    }

    public static function requireKeyForValue(Validator $validator): void
    {
        /** @var array<string, mixed> $data */
        $data = $validator->getData();

        if (self::valueLacksKey($data)) {
            $validator->errors()->add('value', self::VALUE_NEEDS_KEY);
        }
    }

    /**
     * @param  array<string, mixed>  $input  already validated against {@see rules()}
     */
    public static function filters(array $input, bool $hideHealth = true, bool $hideViewer = true): LogRowFilters
    {
        $domain = self::stringOrNull($input, 'domain');

        return new LogRowFilters(
            domain: $domain === null ? null : LogDomain::tryFrom($domain),
            level: self::stringOrNull($input, 'level'),
            phase: self::stringOrNull($input, 'phase'),
            event: self::stringOrNull($input, 'event'),
            requestId: self::stringOrNull($input, 'request'),
            txnId: self::stringOrNull($input, 'txn'),
            sessionId: self::stringOrNull($input, 'session'),
            actorId: self::stringOrNull($input, 'actor'),
            msg: self::stringOrNull($input, 'msg'),
            from: self::stringOrNull($input, 'from'),
            to: self::stringOrNull($input, 'to'),
            key: self::stringOrNull($input, 'key'),
            value: self::stringOrNull($input, 'value'),
            hideHealth: $hideHealth,
            hideViewer: $hideViewer,
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private static function stringOrNull(array $input, string $field): ?string
    {
        $value = $input[$field] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  list<string>  $prefixes
     */
    private static function prefixedIdRule(array $prefixes): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($prefixes): void {
            if (! is_string($value)) {
                $fail('not an id of the expected shape');

                return;
            }

            foreach ($prefixes as $prefix) {
                if (PrefixedId::parse($prefix, $value) !== null) {
                    return;
                }
            }

            $fail('not an id of the expected shape');
        };
    }
}

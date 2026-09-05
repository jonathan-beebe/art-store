<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Identifiers\PrefixedId;
use App\Logging\Admin\LogRowFilters;
use App\Logging\LogDomain;
use App\Logging\StoryEvent;
use App\Logging\StoryLevel;
use App\Logging\StoryPhase;
use Closure;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * `/admin/logs`'s filters: docs/logging.md § "Viewer" and docs/spec.md
 * §5 fix the vocabulary — an empty value means "all", an unrecognised one
 * answers 400. Laravel's `$request->enum()` reads a bad value as absent
 * rather than refusing it, so this class validates explicitly and turns a
 * failure into a bare 400 rather than the framework's default redirect.
 */
final class LogsQueryRequest extends FormRequest
{
    /** The correlation id a caller may set with `X-Request-Id`
     * (`App\Http\Middleware\LogRequestStory`), and so the shape `?request=`
     * accepts. */
    private const string REQUEST_ID_PATTERN = '/^[A-Za-z0-9_-]{1,64}$/';

    private const string ISO_INSTANT_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/';

    private const array ACTOR_PREFIXES = ['adm', 'sel', 'cus'];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'domain' => ['nullable', Rule::enum(LogDomain::class)],
            'level' => ['nullable', Rule::enum(StoryLevel::class)],
            'phase' => ['nullable', Rule::enum(StoryPhase::class)],
            'event' => ['nullable', Rule::enum(StoryEvent::class)],
            'request' => ['nullable', 'string', 'regex:'.self::REQUEST_ID_PATTERN],
            'txn' => ['nullable', $this->prefixedIdRule(['txn'])],
            'session' => ['nullable', $this->prefixedIdRule(['ses'])],
            'actor' => ['nullable', $this->prefixedIdRule(self::ACTOR_PREFIXES)],
            'msg' => ['nullable', 'string'],
            'from' => ['nullable', 'string', 'regex:'.self::ISO_INSTANT_PATTERN],
            'to' => ['nullable', 'string', 'regex:'.self::ISO_INSTANT_PATTERN],
            'key' => ['nullable', 'string', 'regex:'.LogRowFilters::ATTRIBUTE_KEY_PATTERN],
            'value' => ['nullable', 'string'],
            'group' => ['nullable', 'in:1'],
            'health' => ['nullable', 'in:1'],
            'viewer' => ['nullable', 'in:1'],
            'page' => ['nullable', 'string'],
        ];
    }

    /** A value with no key names no attribute to compare it against. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('value') !== null && $this->input('key') === null) {
                $validator->errors()->add('value', 'a value filter needs a key');
            }
        });
    }

    /** An empty value — a `<select>`'s "all" option, an emptied text
     * input — reads as no filter rather than as a value every rule above
     * would otherwise have to admit. */
    protected function prepareForValidation(): void
    {
        $blanked = array_map(
            fn (mixed $value): mixed => $value === '' ? null : $value,
            $this->only(array_keys($this->rules())),
        );

        $this->merge($blanked);
    }

    /** docs/spec.md §5: an unrecognised filter value answers 400 — not
     * the framework's default redirect back with flashed errors. */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response('', 400));
    }

    public function filters(): LogRowFilters
    {
        return new LogRowFilters(
            domain: $this->enum('domain', LogDomain::class),
            level: $this->stringOrNull('level'),
            phase: $this->stringOrNull('phase'),
            event: $this->stringOrNull('event'),
            requestId: $this->stringOrNull('request'),
            txnId: $this->stringOrNull('txn'),
            sessionId: $this->stringOrNull('session'),
            actorId: $this->stringOrNull('actor'),
            msg: $this->stringOrNull('msg'),
            from: $this->stringOrNull('from'),
            to: $this->stringOrNull('to'),
            key: $this->stringOrNull('key'),
            value: $this->stringOrNull('value'),
            hideHealth: $this->input('health') !== '1',
            hideViewer: $this->input('viewer') !== '1',
        );
    }

    public function grouped(): bool
    {
        return $this->input('group') === '1';
    }

    public function page(): ?string
    {
        return $this->stringOrNull('page');
    }

    /** The submitted filters, without `page` — what round-trips through
     * the form, the pager, and the level tiles.
     *
     * @return array<string, string>
     */
    public function roundTrippedFilters(): array
    {
        $fields = ['domain', 'level', 'phase', 'event', 'request', 'txn', 'session', 'actor', 'msg', 'from', 'to', 'key', 'value', 'group', 'health', 'viewer'];

        $filters = [];
        foreach ($fields as $field) {
            $value = $this->stringOrNull($field);
            if ($value !== null) {
                $filters[$field] = $value;
            }
        }

        return $filters;
    }

    private function stringOrNull(string $field): ?string
    {
        $value = $this->input($field);

        return is_string($value) ? $value : null;
    }

    /**
     * @param  list<string>  $prefixes
     */
    private function prefixedIdRule(array $prefixes): Closure
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

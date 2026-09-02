<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\AnalyticsRange;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * `/admin/analytics/listings/{listing}` and `/admin/analytics/actors/{customer}`'s
 * query parameters — docs/alignment.md §5: an empty value means "all", an
 * unrecognised one answers 400. The same shape {@see AnalyticsEventQueryRequest}
 * uses; shared between the two entity pages since both take exactly `range`
 * and `event`.
 */
final class AnalyticsEntityQueryRequest extends FormRequest
{
    private const int DEFAULT_RANGE_DAYS = 30;

    private const string ALL_EVENTS = 'all';

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'range' => ['nullable', Rule::in(array_map(strval(...), AnalyticsRange::SIZES))],
            'event' => ['nullable', Rule::in([
                self::ALL_EVENTS,
                ...array_map(fn (AnalyticsEventName $name): string => $name->value, AnalyticsEventName::cases()),
            ])],
        ];
    }

    /** An empty value — a segmented control's "all" option, an emptied
     * field — reads as absent, so it never reaches the rules above as a
     * value they would have to admit. */
    protected function prepareForValidation(): void
    {
        $blanked = array_map(
            fn (mixed $value): mixed => $value === '' ? null : $value,
            $this->only(array_keys($this->rules())),
        );

        $this->merge($blanked);
    }

    /** docs/alignment.md §5: an unrecognised filter value answers 400 —
     * not the framework's default redirect back with flashed errors. */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response('', 400));
    }

    public function rangeDays(): int
    {
        $value = $this->stringOrNull('range');

        return $value === null ? self::DEFAULT_RANGE_DAYS : (int) $value;
    }

    /** The submitted event filter, or null for "all" — absent and `all`
     * read the same way. */
    public function eventFilter(): ?AnalyticsEventName
    {
        $value = $this->stringOrNull('event');

        return $value === null || $value === self::ALL_EVENTS ? null : AnalyticsEventName::from($value);
    }

    /** The submitted filters, in the shape the range and event segmented
     * controls round-trip.
     *
     * @return array<string, string>
     */
    public function roundTripped(): array
    {
        $filters = [];

        foreach (['range', 'event'] as $field) {
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
}

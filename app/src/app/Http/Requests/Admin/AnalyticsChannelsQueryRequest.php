<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Analytics\AnalyticsRange;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * `/admin/analytics/channels`'s query parameters — docs/spec.md §5: an
 * empty value means "all", an unrecognised one answers 400.
 */
final class AnalyticsChannelsQueryRequest extends FormRequest
{
    private const int DEFAULT_RANGE_DAYS = 30;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'range' => ['nullable', Rule::in(array_map(strval(...), AnalyticsRange::SIZES))],
        ];
    }

    /** An empty value — the range control's own link never carries one,
     * a hand-edited URL might — reads as absent, so it never reaches the
     * rule above as a value it would have to admit. */
    protected function prepareForValidation(): void
    {
        $blanked = array_map(
            fn (mixed $value): mixed => $value === '' ? null : $value,
            $this->only(array_keys($this->rules())),
        );

        $this->merge($blanked);
    }

    /** docs/spec.md §5: an unrecognised filter value answers 400 —
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

    /** The submitted filters, in the shape the range segmented control
     * round-trips.
     *
     * @return array<string, string>
     */
    public function roundTripped(): array
    {
        $value = $this->stringOrNull('range');

        return $value === null ? [] : ['range' => $value];
    }

    private function stringOrNull(string $field): ?string
    {
        $value = $this->input($field);

        return is_string($value) ? $value : null;
    }
}

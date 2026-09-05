<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Analytics\ActorKindFilter;
use App\Domain\Analytics\ActorSort;
use App\Domain\Analytics\AnalyticsRange;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * `/admin/analytics/actors`'s query parameters — docs/spec.md §5: an
 * empty value means "all" (or, for `sort`, the page's default), an
 * unrecognised one answers 400. `page` validates as a positive integer
 * when present; a value past the end of the list is
 * {@see App\Support\Page::of()}'s own concern to clamp.
 */
final class AnalyticsActorsQueryRequest extends FormRequest
{
    private const int DEFAULT_RANGE_DAYS = 30;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'range' => ['nullable', Rule::in(array_map(strval(...), AnalyticsRange::SIZES))],
            'sort' => ['nullable', Rule::enum(ActorSort::class)],
            'actors' => ['nullable', Rule::enum(ActorKindFilter::class)],
            'q' => ['nullable', 'string'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /** An empty value — a cleared search box, a segmented control's "all"
     * option carrying no query value — reads as no filter, so it never
     * reaches the rules above as a value they would have to admit. */
    protected function prepareForValidation(): void
    {
        $blanked = array_map(
            fn (mixed $value): mixed => $value === '' ? null : $value,
            $this->only(array_keys($this->rules())),
        );

        $this->merge($blanked);
    }

    /** docs/spec.md §5: an unrecognised filter value answers a bare 400.
     * The framework's default redirect back with flashed errors never
     * fires. */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response('', 400));
    }

    public function rangeDays(): int
    {
        $value = $this->stringOrNull('range');

        return $value === null ? self::DEFAULT_RANGE_DAYS : (int) $value;
    }

    public function sort(): ActorSort
    {
        return $this->enum('sort', ActorSort::class) ?? ActorSort::Active;
    }

    public function actorKind(): ActorKindFilter
    {
        return $this->enum('actors', ActorKindFilter::class) ?? ActorKindFilter::All;
    }

    public function search(): ?string
    {
        return $this->stringOrNull('q');
    }

    public function page(): int
    {
        return $this->integer('page', 1);
    }

    /** The submitted filters, without `page` — the shape every segmented
     * control's link and the search form's hidden fields round-trip.
     *
     * @return array<string, string>
     */
    public function roundTripped(): array
    {
        $filters = [];

        foreach (['range', 'sort', 'actors', 'q'] as $field) {
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

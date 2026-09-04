<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Seller\ActivityKind;
use App\Domain\Seller\CustomerSegment;
use App\Domain\Seller\CustomerSort;
use App\Domain\Seller\CustomerSortColumn;
use App\Domain\Seller\SortDirection;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * The customers tool's query vocabulary, shared by `GET /seller/customers`
 * (`range`, `segment`, `sort`, `dir`) and `GET /seller/customers/{customer}`
 * (`kind`) — docs/alignment.md §5: an absent or emptied value reads as the
 * default, and an unrecognised value answers a bare 400.
 */
final class CustomersQueryRequest extends FormRequest
{
    private const int DEFAULT_RANGE_DAYS = 30;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'range' => ['nullable', Rule::in(array_map(strval(...), AnalyticsRange::SIZES))],
            'segment' => ['nullable', Rule::enum(CustomerSegment::class)],
            'sort' => ['nullable', Rule::enum(CustomerSortColumn::class)],
            'dir' => ['nullable', Rule::enum(SortDirection::class)],
            'kind' => ['nullable', Rule::enum(ActivityKind::class)],
        ];
    }

    /** An emptied value reads as absent, so the rules above see no value to validate. */
    protected function prepareForValidation(): void
    {
        $blanked = array_map(
            fn (mixed $value): mixed => $value === '' ? null : $value,
            $this->only(array_keys($this->rules())),
        );

        $this->merge($blanked);
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response('', 400));
    }

    public function rangeDays(): int
    {
        $value = $this->stringOrNull('range');

        return $value === null ? self::DEFAULT_RANGE_DAYS : (int) $value;
    }

    /**
     * Named for the buyers it narrows to: `segment()` alone is
     * {@see \Illuminate\Http\Request::segment()}, a path segment.
     */
    public function customerSegment(): CustomerSegment
    {
        return $this->enum('segment', CustomerSegment::class) ?? CustomerSegment::default();
    }

    /** Any `sort` or `dir` in the query sets the sort; neither present keeps the default. */
    public function sort(): CustomerSort
    {
        $column = $this->enum('sort', CustomerSortColumn::class);
        $direction = $this->enum('dir', SortDirection::class);

        return $column === null && $direction === null
            ? CustomerSort::default()
            : CustomerSort::of($column ?? CustomerSortColumn::Spent, $direction ?? SortDirection::Desc);
    }

    /** The timeline's filter. Absent is the whole feed. */
    public function kind(): ?ActivityKind
    {
        return $this->enum('kind', ActivityKind::class);
    }

    /**
     * The submitted filters, in the shape every segment and sort link
     * round-trips.
     *
     * @return array<string, string>
     */
    public function roundTripped(): array
    {
        $filters = [];

        foreach (['range', 'segment', 'sort', 'dir'] as $field) {
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

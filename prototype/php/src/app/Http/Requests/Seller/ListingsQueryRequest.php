<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Seller\ListingSort;
use App\Domain\Seller\ListingSortColumn;
use App\Domain\Seller\ListingSortDirection;
use App\Domain\Seller\ListingView;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * The listings tool's query vocabulary, shared by `GET /seller/listings`
 * (`view`, `sort`, `dir`, `range`) and `GET /seller/listings/{listing}`
 * (`from`, `sort`, `dir`, `range`) — docs/alignment.md §5: an absent or
 * emptied value reads as the default, and an unrecognised value answers a
 * bare 400 rather than the framework's default redirect back with flashed
 * errors. `from` names the view a detail row was opened from: absent for
 * the list pane's own detail, `table` or `grid` for the overlay/takeover.
 */
final class ListingsQueryRequest extends FormRequest
{
    private const int DEFAULT_RANGE_DAYS = 30;

    public const array FROM_VIEWS = ['table', 'grid'];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'view' => ['nullable', Rule::enum(ListingView::class)],
            'from' => ['nullable', Rule::in(self::FROM_VIEWS)],
            'sort' => ['nullable', Rule::enum(ListingSortColumn::class)],
            'dir' => ['nullable', Rule::enum(ListingSortDirection::class)],
            'range' => ['nullable', Rule::in(array_map(strval(...), AnalyticsRange::SIZES))],
        ];
    }

    /** An emptied value reads as absent rather than as a value the rules
     * above would otherwise have to admit. */
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

    public function view(): ListingView
    {
        return $this->enum('view', ListingView::class) ?? ListingView::default();
    }

    public function from(): ?string
    {
        return $this->stringOrNull('from');
    }

    /** Any `sort` or `dir` in the query sets the sort; neither present keeps the default. */
    public function sort(): ListingSort
    {
        $column = $this->enum('sort', ListingSortColumn::class);
        $direction = $this->enum('dir', ListingSortDirection::class);

        return $column === null && $direction === null
            ? ListingSort::default()
            : ListingSort::of($column ?? ListingSortColumn::Views, $direction ?? ListingSortDirection::Desc);
    }

    public function rangeDays(): int
    {
        $value = $this->stringOrNull('range');

        return $value === null ? self::DEFAULT_RANGE_DAYS : (int) $value;
    }

    /** The submitted filters, in the shape every view/sort/range link round-trips.
     *
     * @return array<string, string>
     */
    public function roundTripped(): array
    {
        $filters = [];

        foreach (['view', 'sort', 'dir', 'range'] as $field) {
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

<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * The seller query vocabulary every list/detail route's `?…` shares
 * (docs/spec.md §5): an absent or emptied value reads as the
 * default, and an unrecognised value answers a bare 400. `range` is not
 * part of this shared vocabulary — the dashboard is the one page that
 * reads a range, and owns `rangeDays()` itself
 * ({@see DashboardQueryRequest}); a stray `?range=` on every other seller
 * route is a key `rules()` never names, so it validates nothing and
 * changes nothing.
 */
abstract class SellerQueryRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    abstract public function rules(): array;

    /** An emptied value reads as absent, so `rules()` sees no value to validate. */
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

    protected function stringOrNull(string $field): ?string
    {
        $value = $this->input($field);

        return is_string($value) ? $value : null;
    }

    /**
     * The submitted filters named by `$fields`, in the shape every link
     * on the page round-trips.
     *
     * @param  list<string>  $fields
     * @return array<string, string>
     */
    protected function roundTrippedOf(array $fields): array
    {
        $filters = [];

        foreach ($fields as $field) {
            $value = $this->stringOrNull($field);

            if ($value !== null) {
                $filters[$field] = $value;
            }
        }

        return $filters;
    }
}

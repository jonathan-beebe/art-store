<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Analytics\AnalyticsRange;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * The dashboard's query vocabulary: `range`, the window every tile, total,
 * and strip on the page is read over — docs/alignment.md §5: an absent or
 * emptied value reads as the default, and an unrecognised value answers a
 * bare 400.
 */
final class DashboardQueryRequest extends FormRequest
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

    /** An emptied value reads as absent, so the rule above sees no value to validate. */
    protected function prepareForValidation(): void
    {
        if ($this->input('range') === '') {
            $this->merge(['range' => null]);
        }
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response('', 400));
    }

    public function rangeDays(): int
    {
        $value = $this->input('range');

        return is_string($value) ? (int) $value : self::DEFAULT_RANGE_DAYS;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * The seller inbox's `?filter=` and `?status=` (docs/messaging.md § "Inbox
 * filters and the seller's queue"): an absent or empty value reads as the
 * default, an unrecognised one answers a bare 400 (docs/alignment.md §5)
 * rather than the framework's default redirect back with flashed errors.
 */
final class MessagesQueryRequest extends FormRequest
{
    public const string DEFAULT_FILTER = 'all';

    public const string DEFAULT_STATUS = 'open';

    private const array FILTERS = ['all', 'unread', 'questions', 'orders', 'support'];

    private const array STATUSES = ['open', 'resolved', 'all'];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', Rule::in(self::FILTERS)],
            'status' => ['nullable', Rule::in(self::STATUSES)],
        ];
    }

    /** An emptied value reads as absent rather than as a value the rule above would otherwise have to admit. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'filter' => $this->filled('filter') ? $this->input('filter') : null,
            'status' => $this->filled('status') ? $this->input('status') : null,
        ]);
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response('', 400));
    }

    public function filter(): string
    {
        return $this->stringOrDefault('filter', self::DEFAULT_FILTER);
    }

    public function status(): string
    {
        return $this->stringOrDefault('status', self::DEFAULT_STATUS);
    }

    /**
     * `input($key, $default)` falls back to `$default` only when the key is
     * entirely absent — `prepareForValidation` above leaves a blanked value
     * present with a `null` value, which `input()` returns as-is rather than
     * defaulting, so the fallback is applied here instead.
     */
    private function stringOrDefault(string $key, string $default): string
    {
        $value = $this->input($key);

        return is_string($value) && $value !== '' ? $value : $default;
    }
}

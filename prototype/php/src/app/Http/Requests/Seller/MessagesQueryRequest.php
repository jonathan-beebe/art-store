<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * The seller inbox's `?domain=` (docs/messaging.md § "Inbox domains"): an
 * absent or emptied value reads as the default, and an unrecognised value
 * answers a bare 400 (docs/alignment.md §5) rather than the framework's
 * default redirect back with flashed errors.
 */
final class MessagesQueryRequest extends FormRequest
{
    public const string DEFAULT_DOMAIN = 'all';

    public const array DOMAINS = ['all', 'buyers', 'support'];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'domain' => ['nullable', Rule::in(self::DOMAINS)],
        ];
    }

    /** An emptied `domain` reads as absent rather than as a value the rule
     * above would otherwise have to admit. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'domain' => $this->filled('domain') ? $this->input('domain') : null,
        ]);
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response('', 400));
    }

    /** The current domain tab, defaulted when absent. */
    public function domain(): string
    {
        $domain = $this->input('domain');

        return is_string($domain) && $domain !== '' ? $domain : self::DEFAULT_DOMAIN;
    }
}

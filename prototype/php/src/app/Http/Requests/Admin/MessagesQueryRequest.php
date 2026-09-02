<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * `/admin/messages`'s two filters — docs/messaging.md § "Inbox filters and
 * the seller's queue" fixes the admin vocabulary, docs/alignment.md §5 says
 * an unrecognised value answers 400 rather than the framework's default
 * redirect. The desk's default landing is its work queue: `needs-reply`
 * threads that are `open`.
 */
final class MessagesQueryRequest extends FormRequest
{
    private const array FILTERS = ['needs-reply', 'all', 'sellers', 'customers', 'orders', 'questions'];

    private const array STATUSES = ['open', 'resolved', 'all'];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', 'string', Rule::in(self::FILTERS)],
            'status' => ['nullable', 'string', Rule::in(self::STATUSES)],
        ];
    }

    /** An emptied `<select>`'s "blank" option reads as absent rather than
     * as a value neither rule above would admit. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'filter' => $this->input('filter') === '' ? null : $this->input('filter'),
            'status' => $this->input('status') === '' ? null : $this->input('status'),
        ]);
    }

    /** docs/alignment.md §5: an unrecognised filter value answers a bare
     * 400, not the framework's redirect-back-with-errors. */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response('', 400));
    }

    public function filter(): string
    {
        return $this->stringOrNull('filter') ?? 'needs-reply';
    }

    public function status(): string
    {
        return $this->stringOrNull('status') ?? 'open';
    }

    /**
     * The show route's own default: unlike the index route, a thread page
     * with no `filter`/`status` of its own (a direct or bookmarked visit,
     * or a link from outside the inbox) reads as the desk's full,
     * unscoped list rather than its work queue — `needs-reply` would
     * otherwise exclude an oversight or already-answered thread from its
     * own pane. A row link out of a filtered inbox still carries its
     * `filter`/`status` onward, so this default only applies absent one.
     */
    public function paneFilter(): string
    {
        return $this->stringOrNull('filter') ?? 'all';
    }

    public function paneStatus(): string
    {
        return $this->stringOrNull('status') ?? 'all';
    }

    private function stringOrNull(string $field): ?string
    {
        $value = $this->input($field);

        return is_string($value) ? $value : null;
    }
}

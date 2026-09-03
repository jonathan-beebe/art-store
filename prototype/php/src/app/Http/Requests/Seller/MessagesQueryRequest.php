<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Support\Messaging\InboxQuery;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * The seller inbox's `?domain=`, `?type[]=`, and `?status[]=`
 * (docs/messaging.md § "Inbox filters and the seller's queue"): an absent or
 * emptied `domain` reads as the default, and an unrecognised value —
 * including an unknown member of `type[]`/`status[]`, or a `type`/`status`
 * that isn't an array at all — answers a bare 400 (docs/alignment.md §5)
 * rather than the framework's default redirect back with flashed errors.
 */
final class MessagesQueryRequest extends FormRequest
{
    public const string DEFAULT_DOMAIN = 'all';

    public const array DOMAINS = ['all', 'buyers', 'support'];

    public const array TYPES = ['questions', 'orders', 'support'];

    public const array STATUSES = ['open', 'resolved'];

    public const array DEFAULT_STATUSES = ['open'];

    /** Every status — the show route's own default, so a resolved thread
     * still lands in its own pane. */
    public const array EVERY_STATUS = ['open', 'resolved'];

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'domain' => ['nullable', Rule::in(self::DOMAINS)],
            'type' => ['nullable', 'array'],
            'type.*' => [Rule::in(self::TYPES)],
            'status' => ['nullable', 'array'],
            'status.*' => [Rule::in(self::STATUSES)],
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

    /** The index route's query: an absent `status[]` defaults to Open only. */
    public function inboxQuery(): InboxQuery
    {
        return $this->buildQuery(self::DEFAULT_STATUSES);
    }

    /** The show route's list pane: an absent `status[]` defaults to every
     * status, so a direct or bookmarked visit to a resolved thread still
     * lands in its own pane. */
    public function paneQuery(): InboxQuery
    {
        return $this->buildQuery(self::EVERY_STATUS);
    }

    /**
     * @param  list<string>  $defaultStatuses
     */
    private function buildQuery(array $defaultStatuses): InboxQuery
    {
        $domain = $this->input('domain');

        /** @var list<string>|null $types */
        $types = $this->input('type');
        /** @var list<string>|null $statuses */
        $statuses = $this->input('status');

        return new InboxQuery(
            is_string($domain) && $domain !== '' ? $domain : self::DEFAULT_DOMAIN,
            $types ?? self::TYPES,
            $statuses ?? $defaultStatuses,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Domain\Messaging\ConversationStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * `/messages`'s two filter pills — docs/spec.md §5: an empty value
 * means "all". An unrecognised one answers a bare 400; the framework's
 * default validation redirect never fires.
 */
final class ShopMessagesIndexRequest extends ShopRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'filter' => ['nullable', Rule::in(['all', 'unread'])],
            'status' => ['nullable', Rule::in(['open', 'resolved', 'all'])],
        ];
    }

    /** An empty value — a cleared query parameter — reads as absent, so the
     * rules above never have to admit it as a value. */
    protected function prepareForValidation(): void
    {
        $blanked = array_map(
            fn (mixed $value): mixed => $value === '' ? null : $value,
            $this->only(['filter', 'status']),
        );

        $this->merge($blanked);
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response('', 400));
    }

    public function filter(): string
    {
        return $this->stringOrNull('filter') ?? 'all';
    }

    /** The raw value a filter pill's `aria-current` and `href` compare
     * against — `status()` collapses `all` to null for the query, which
     * loses the word a pill needs to recognise itself. */
    public function statusValue(): string
    {
        return $this->stringOrNull('status') ?? 'open';
    }

    public function status(): ?ConversationStatus
    {
        $value = $this->statusValue();

        return $value === 'all' ? null : ConversationStatus::from($value);
    }

    private function stringOrNull(string $key): ?string
    {
        $value = $this->input($key);

        return is_string($value) ? $value : null;
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Identifiers\PrefixedId;
use App\Domain\Messaging\MessageBody;
use App\Domain\Messaging\ThreadTitle;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Opens a fresh `admin_seller` thread from the seller's own "New
 * conversation" form: a subject, a message, and — since the desk answers
 * faster with the order beside it — an optional order of the seller's own.
 */
final class OpenSupportThreadRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:'.ThreadTitle::MAX_LENGTH],
            'body' => ['required', 'string', 'max:'.MessageBody::MAX_LENGTH],
            // Scoped to this seller's own fulfillments, so a tampered id
            // cannot attach another seller's order to the thread.
            'fulfillment_id' => [
                'nullable',
                'string',
                'size:'.PrefixedId::LENGTH,
                Rule::exists('fulfillments', 'id')->where(
                    fn (Builder $query) => $query->where('seller_id', $this->user('seller')?->id)
                ),
            ],
        ];
    }

    public function title(): ThreadTitle
    {
        return ThreadTitle::of($this->string('title')->toString());
    }

    public function body(): MessageBody
    {
        return MessageBody::of($this->string('body')->toString());
    }

    public function fulfillmentId(): ?string
    {
        return $this->filled('fulfillment_id') ? $this->string('fulfillment_id')->toString() : null;
    }
}

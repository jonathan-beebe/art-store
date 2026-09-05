<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Messaging\MessageBody;
use App\Domain\Messaging\ThreadTitle;
use App\Models\Seller;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use RuntimeException;

/**
 * Opens a fresh, titled admin/seller thread from the seller's detail page.
 * The optional order is one of this seller's own fulfillments — the context
 * column `ThreadOpening::adminSeller()` carries — never an id read off the
 * form unchecked.
 */
final class OpenSellerThreadRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:'.ThreadTitle::MAX_LENGTH],
            'body' => ['required', 'string', 'max:'.MessageBody::MAX_LENGTH],
            'fulfillment' => ['nullable', 'string'],
        ];
    }

    /** An emptied "No order" `<select>` option reads as no context. */
    protected function prepareForValidation(): void
    {
        $this->merge(['fulfillment' => $this->input('fulfillment') === '' ? null : $this->input('fulfillment')]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $fulfillmentId = $this->fulfillmentId();

            if ($fulfillmentId !== null && ! $this->seller()->fulfillments()->whereKey($fulfillmentId)->exists()) {
                $validator->errors()->add('fulfillment', 'Choose one of this seller\'s own orders.');
            }
        });
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
        $value = $this->string('fulfillment')->toString();

        return $value === '' ? null : $value;
    }

    private function seller(): Seller
    {
        $seller = $this->route('seller');

        return $seller instanceof Seller
            ? $seller
            : throw new RuntimeException('The route binds a seller.');
    }
}

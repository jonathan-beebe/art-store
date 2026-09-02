<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Domain\Messaging\MessageBody;
use App\Domain\Messaging\ThreadTitle;
use App\Models\Customer;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use RuntimeException;

/**
 * Opens a fresh, titled admin/customer thread from the customer's detail
 * page, the customer-side twin of `OpenSellerThreadRequest`. The optional
 * order is one of this customer's own orders — the context column
 * `ThreadOpening::adminCustomer()` carries.
 */
final class OpenCustomerThreadRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:'.ThreadTitle::MAX_LENGTH],
            'body' => ['required', 'string', 'max:'.MessageBody::MAX_LENGTH],
            'order' => ['nullable', 'string'],
        ];
    }

    /** An emptied "No order" `<select>` option reads as no context. */
    protected function prepareForValidation(): void
    {
        $this->merge(['order' => $this->input('order') === '' ? null : $this->input('order')]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $orderId = $this->orderId();

            if ($orderId !== null && ! $this->customer()->orders()->whereKey($orderId)->exists()) {
                $validator->errors()->add('order', 'Choose one of this customer\'s own orders.');
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

    public function orderId(): ?string
    {
        $value = $this->string('order')->toString();

        return $value === '' ? null : $value;
    }

    private function customer(): Customer
    {
        $customer = $this->route('customer');

        return $customer instanceof Customer
            ? $customer
            : throw new RuntimeException('The route binds a customer.');
    }
}

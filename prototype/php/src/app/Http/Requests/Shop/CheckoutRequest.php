<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Domain\Orders\Purchaser;
use App\Domain\Orders\ShippingAddress;
use App\Models\Customer;
use Illuminate\Validation\Rule;
use Stringable;

final class CheckoutRequest extends ShopRequest
{
    /**
     * @return array<string, list<string|Stringable>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'shipping_name' => ['required', 'string', 'max:255'],
            'shipping_line1' => ['required', 'string', 'max:255'],
            'shipping_line2' => ['nullable', 'string', 'max:255'],
            'shipping_city' => ['required', 'string', 'max:255'],
            'shipping_region' => ['required', 'string', 'max:255'],
            'shipping_postal_code' => ['required', 'string', 'max:32'],
            'shipping_country' => ['required', 'string', 'max:64'],
            // An unverified order has nowhere to hold a card until a link
            // verifies the address behind it, so the checkout form neither
            // shows the field nor asks for it until the visitor is verified.
            'card_number' => [Rule::requiredIf($this->visitor()->isVerified()), 'nullable', 'string', 'max:32'],
        ];
    }

    public function email(): string
    {
        return $this->string('email')->toString();
    }

    public function cardNumber(): string
    {
        return $this->string('card_number')->toString();
    }

    public function toShippingAddress(): ShippingAddress
    {
        return ShippingAddress::to(
            name: $this->string('shipping_name')->toString(),
            line1: $this->string('shipping_line1')->toString(),
            line2: $this->filled('shipping_line2') ? $this->string('shipping_line2')->toString() : null,
            city: $this->string('shipping_city')->toString(),
            region: $this->string('shipping_region')->toString(),
            postalCode: $this->string('shipping_postal_code')->toString(),
            country: $this->string('shipping_country')->toString(),
        );
    }

    public function toPurchaser(Customer $visitor): Purchaser
    {
        return Purchaser::forCheckout(
            customerId: $visitor->id,
            accountEmail: $visitor->email,
            emailVerifiedAt: $visitor->email_verified_at?->toDateTimeImmutable(),
            submittedEmail: $this->email(),
        );
    }
}

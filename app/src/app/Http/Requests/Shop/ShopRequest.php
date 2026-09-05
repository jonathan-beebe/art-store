<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Models\Customer;
use App\Shop\CustomerIdentity;
use Illuminate\Foundation\Http\FormRequest;
use Override;
use RuntimeException;

/**
 * Shared ground for the storefront forms: the visitor the middleware resolved,
 * which the rules read before the controller ever sees the submission.
 */
abstract class ShopRequest extends FormRequest
{
    /**
     * The fields a card arrives in. Nothing flashes them: `<x-card-fields>`
     * renders `old('card_number')` back into the field, so a flashed card
     * number would be written into the next response body and held in session
     * storage until it expires. bootstrap/app.php keeps the same fields out of
     * the two flashes the framework does on its own — the validation redirect
     * and a `DomainRuleViolation`'s `back()->withInput()`.
     */
    public const array CARD_FIELDS = ['card_number', 'card_expiry', 'card_cvc'];

    /**
     * Everything the shopper typed except the card, so a form that re-renders
     * comes back filled in without the number going anywhere.
     */
    #[Override]
    public function flash(): void
    {
        $this->flashExcept(self::CARD_FIELDS);
    }

    protected function visitor(): Customer
    {
        return CustomerIdentity::current() ?? throw new RuntimeException('The storefront runs behind the customer.identity middleware.');
    }
}

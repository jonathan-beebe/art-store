<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Models\Customer;
use App\Support\CustomerIdentity;
use Illuminate\Foundation\Http\FormRequest;
use RuntimeException;

/**
 * Shared ground for the storefront forms: the visitor the middleware resolved,
 * which the rules read before the controller ever sees the submission.
 */
abstract class ShopRequest extends FormRequest
{
    protected function visitor(): Customer
    {
        return CustomerIdentity::current() ?? throw new RuntimeException('The storefront runs behind the customer.identity middleware.');
    }
}

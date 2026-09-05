<?php

declare(strict_types=1);

namespace App\Http\Requests\Shop;

use App\Models\Order;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use RuntimeException;

final class PayOrderRequest extends ShopRequest
{
    /**
     * Another customer's order is answered before its card field is read, so a
     * validation message never confirms an order they cannot see.
     */
    public function authorize(): Response
    {
        return Gate::forUser($this->visitor())->inspect('pay', $this->order());
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'card_number' => ['required', 'string', 'max:32'],
        ];
    }

    public function cardNumber(): string
    {
        return $this->string('card_number')->toString();
    }

    private function order(): Order
    {
        $order = $this->route('order');

        return $order instanceof Order
            ? $order
            : throw new RuntimeException('The pay route binds an order.');
    }
}

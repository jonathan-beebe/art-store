<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Customers\BlockCustomer;
use App\Http\Requests\Admin\BlockCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;

final class CustomerBlockController extends AdminController
{
    public function __invoke(BlockCustomerRequest $request, Customer $customer, BlockCustomer $blockCustomer): RedirectResponse
    {
        $blockCustomer($customer, $request->reason());

        return redirect()->route('admin.customers.show', $customer)->with('status', 'Customer blocked.');
    }
}

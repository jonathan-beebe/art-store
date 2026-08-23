<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Customers\LiftCustomerBlock;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;

final class LiftCustomerBlockController extends AdminController
{
    public function __invoke(Customer $customer, LiftCustomerBlock $liftCustomerBlock): RedirectResponse
    {
        $liftCustomerBlock($customer, $this->now());

        return redirect()->route('admin.customers.show', $customer)->with('status', 'Block lifted.');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\View\View;

final class CustomerController extends AdminController
{
    public function index(): View
    {
        return view('admin.customers.index', [
            'customers' => Customer::query()->with('activeBlock')->latest('id')->get(),
        ]);
    }

    public function show(Customer $customer): View
    {
        return view('admin.customers.show', [
            'customer' => $customer->load([
                'activeBlock',
                'orders' => fn (Relation $orders) => $orders->latest('id'),
            ]),
        ]);
    }
}

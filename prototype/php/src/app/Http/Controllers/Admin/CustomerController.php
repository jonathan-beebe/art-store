<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\View\View;

final class CustomerController extends Controller
{
    public function index(): View
    {
        return view('admin.customers.index', [
            'customers' => Customer::query()->with('activeBlock')->orderByDesc('created_at')->orderByDesc('id')->get(),
        ]);
    }

    public function show(Customer $customer): View
    {
        return view('admin.customers.show', [
            'customer' => $customer->load([
                'activeBlock',
                'orders' => fn (Relation $orders) => $orders->orderByDesc('placed_at')->orderByDesc('id'),
            ]),
        ]);
    }
}

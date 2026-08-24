<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Customers\StandingFilter;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        // An empty `standing=` is what the console submits for "All
        // customers", and it reads as no filter at all.
        $standing = $request->enum('standing', StandingFilter::class) ?? StandingFilter::All;

        return view('admin.customers.index', [
            'customers' => Customer::query()
                ->inStanding($standing)
                ->with('activeBlock')
                ->withCount(['orders', 'favorites', 'cartItems'])
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get(),
            'standing' => $standing,
            'standings' => StandingFilter::cases(),
        ]);
    }

    public function show(Customer $customer): View
    {
        return view('admin.customers.show', ['customer' => $customer->loadForConsole()]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Customers\StandingFilter;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Support\ListPaneWindow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        // An empty `standing=` is what the console submits for "All
        // customers", and it reads as no filter at all.
        $standing = $request->enum('standing', StandingFilter::class) ?? StandingFilter::All;
        $window = ListPaneWindow::of($this->customersQuery($standing));

        return view('admin.customers.index', [
            'customers' => $window->items,
            'customersTotal' => $window->total,
            'standing' => $standing,
            'standings' => StandingFilter::cases(),
        ]);
    }

    public function show(Customer $customer): View
    {
        // DSGN-006: the show route's list pane is the same default,
        // unfiltered list the index route opens with.
        $window = ListPaneWindow::of($this->customersQuery(StandingFilter::All), $customer);

        return view('admin.customers.show', [
            'customer' => $customer->loadForConsole(),
            'cellCustomers' => $window->items,
            'cellCustomersTotal' => $window->total,
        ]);
    }

    /**
     * @return Builder<Customer>
     */
    private function customersQuery(StandingFilter $standing): Builder
    {
        return Customer::query()
            ->inStanding($standing)
            ->with('activeBlock')
            ->withCount(['orders', 'favorites', 'cartItems'])
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }
}

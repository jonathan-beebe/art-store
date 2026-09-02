<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Messaging\OpenThread;
use App\Domain\Customers\StandingFilter;
use App\Domain\Messaging\ThreadOpening;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Admin\OpenCustomerThreadRequest;
use App\Models\Customer;
use App\Support\ListPaneWindow;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Opens a fresh, titled admin/customer thread from the customer's detail
 * page, the customer-side twin of `SellerMessageController`.
 */
final class CustomerMessageController extends AdminController
{
    public function __invoke(
        Customer $customer,
        OpenCustomerThreadRequest $request,
        OpenThread $send,
        RateLimitGate $rateLimit,
    ): RedirectResponse|Response {
        $admin = $this->admin();

        try {
            $rateLimit->check(RateLimitName::ConversationOpen, (string) $admin->id);
        } catch (RateLimitExceeded $exceeded) {
            // docs/alignment.md §3: a form that trips comes back rather than
            // being replaced by the site's bare 429 page, so the customer
            // page the form sits on re-renders with the message still in the
            // box.
            $request->flash();

            return $this->tooManyRequests($exceeded, 'admin.customers.show', $this->customerPage($customer));
        }

        $conversation = $send(
            ThreadOpening::adminCustomer($customer->id, $request->title(), $request->orderId()),
            $admin,
            $request->body(),
            $this->now(),
        );

        return redirect()->route('admin.messages.show', $conversation);
    }

    /**
     * The customer page the message form sits on, the same data
     * `CustomerController::show` renders it from.
     *
     * @return array<string, mixed>
     */
    private function customerPage(Customer $customer): array
    {
        // DSGN-006: the show page's list pane, windowed the same way
        // `CustomerController::show` windows it (`ListPaneWindow`, DSGN-006
        // follow-up).
        $window = ListPaneWindow::of(
            Customer::query()
                ->inStanding(StandingFilter::All)
                ->with('activeBlock')
                ->withCount(['orders', 'favorites', 'cartItems'])
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
            $customer,
        );

        return [
            'customer' => $customer->loadForConsole(),
            'cellCustomers' => $window->items,
            'cellCustomersTotal' => $window->total,
        ];
    }
}

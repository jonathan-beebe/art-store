<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Messaging\OpenConversationWithMessage;
use App\Domain\Messaging\ConversationSubject;
use App\Domain\RateLimiting\RateLimitExceeded;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Admin\SendMessageRequest;
use App\Models\Customer;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

final class CustomerMessageController extends AdminController
{
    public function __invoke(
        Customer $customer,
        SendMessageRequest $request,
        OpenConversationWithMessage $send,
        RateLimitGate $rateLimit,
    ): RedirectResponse|Response {
        $admin = $this->admin();

        try {
            $rateLimit->check(RateLimitName::MessagePost, (string) $admin->id);
        } catch (RateLimitExceeded $exceeded) {
            // docs/alignment.md §3: a form that trips comes back rather than
            // being replaced by the site's bare 429 page, so the customer
            // page the form sits on re-renders with the message still in the
            // box.
            $request->flash();

            return $this->tooManyRequests($exceeded, 'admin.customers.show', [
                'customer' => $customer->loadForConsole(),
            ]);
        }

        $conversation = $send(
            ConversationSubject::adminCustomer($admin->id, $customer->id),
            $admin,
            $request->body(),
            $this->now(),
        );

        return redirect()->route('admin.messages.show', $conversation);
    }
}

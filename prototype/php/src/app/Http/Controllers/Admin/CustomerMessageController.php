<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Messaging\OpenConversation;
use App\Actions\Messaging\PostMessage;
use App\Domain\Messaging\ConversationSubject;
use App\Http\Requests\Admin\SendMessageRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;

final class CustomerMessageController extends AdminController
{
    public function __invoke(
        Customer $customer,
        SendMessageRequest $request,
        OpenConversation $openConversation,
        PostMessage $postMessage,
    ): RedirectResponse {
        $admin = $this->admin();

        $conversation = $openConversation(
            ConversationSubject::adminCustomer($admin->id, $customer->id),
            $this->now(),
        );

        $postMessage($conversation, $admin, $request->body(), $this->now());

        return redirect()->route('admin.messages.show', $conversation);
    }
}

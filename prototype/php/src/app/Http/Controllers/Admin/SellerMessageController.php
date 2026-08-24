<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Messaging\OpenConversation;
use App\Actions\Messaging\PostMessage;
use App\Domain\Messaging\ConversationSubject;
use App\Domain\RateLimiting\RateLimitName;
use App\Http\Requests\Admin\SendMessageRequest;
use App\Models\Seller;
use App\Support\RateLimiting\RateLimitGate;
use Illuminate\Http\RedirectResponse;

final class SellerMessageController extends AdminController
{
    public function __invoke(
        Seller $seller,
        SendMessageRequest $request,
        OpenConversation $openConversation,
        PostMessage $postMessage,
        RateLimitGate $rateLimit,
    ): RedirectResponse {
        $admin = $this->admin();
        $rateLimit->check(RateLimitName::MessagePost, (string) $admin->id);

        $conversation = $openConversation(
            ConversationSubject::adminSeller($admin->id, $seller->id),
            $this->now(),
        );

        $postMessage($conversation, $admin, $request->body(), $this->now());

        return redirect()->route('admin.messages.show', $conversation);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Messaging\OpenConversation;
use App\Domain\Messaging\ConversationSubject;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;

final class SupportController extends ShopController
{
    public function __invoke(OpenConversation $openConversation): RedirectResponse
    {
        $admin = Admin::platformAdmin();

        if ($admin === null) {
            return back()->withErrors(['support' => 'No admin is available right now.']);
        }

        $conversation = $openConversation(
            ConversationSubject::adminCustomer($admin->id, $this->visitor()->id),
            $this->now(),
        );

        return redirect()->route('shop.messages.show', $conversation);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Actions\Messaging\OpenConversation;
use App\Domain\Messaging\ConversationSubject;
use App\Models\Admin;
use Illuminate\Http\RedirectResponse;

final class SupportController extends SellerController
{
    public function __invoke(OpenConversation $openConversation): RedirectResponse
    {
        $admin = Admin::platformAdmin();

        if ($admin === null) {
            return back()->withErrors(['support' => 'No admin is available right now.']);
        }

        $conversation = $openConversation(
            ConversationSubject::adminSeller($admin->id, $this->seller()->id),
            $this->now(),
        );

        return redirect()->route('seller.messages.show', $conversation);
    }
}

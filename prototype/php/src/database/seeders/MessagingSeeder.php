<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Messaging\MarkConversationRead;
use App\Actions\Messaging\OpenConversation;
use App\Actions\Messaging\PostMessage;
use App\Actions\Messaging\PublishListingFaq;
use App\Domain\Messaging\ConversationSubject;
use App\Domain\Messaging\FaqDraft;
use App\Domain\Messaging\MessageBody;
use App\Models\Admin;
use App\Models\Customer;
use App\Models\Fulfillment;
use App\Models\Listing;
use App\Models\OrderItem;
use App\Models\Seller;
use DateTimeImmutable;
use Illuminate\Database\Seeder;

/**
 * One conversation of each kind, so every messaging inbox in the demo has
 * something on it: a listing question that becomes the storefront's one
 * published FAQ, a thread on a shipped fulfillment, and a support thread for
 * a seller and for the customer. Runs last, after `AdminSeeder` (the support
 * threads need the admin id) and `OrderHistorySeeder` (the fulfillment
 * thread needs a real fulfillment).
 */
class MessagingSeeder extends Seeder
{
    public const FAQ_LISTING_TITLE = 'Woodfired Vase, Tall';

    private const FULFILLMENT_LISTING_TITLE = 'Kitchen Table, Late Morning';

    public function run(): void
    {
        $admin = Admin::where('email', AdminSeeder::ADMINS[0]['email'])->firstOrFail();
        $priya = Seller::where('email', SellerSeeder::PRIYA_EMAIL)->firstOrFail();
        $noah = Seller::where('email', SellerSeeder::NOAH_EMAIL)->firstOrFail();
        $casey = Customer::where('email', CustomerSeeder::CASEY_EMAIL)->firstOrFail();
        $vase = Listing::where('title', self::FAQ_LISTING_TITLE)->firstOrFail();

        $orderItem = OrderItem::where('title', self::FULFILLMENT_LISTING_TITLE)->firstOrFail();
        $fulfillment = Fulfillment::where('order_id', $orderItem->order_id)
            ->where('seller_id', $noah->id)
            ->firstOrFail();

        $this->seedListingQuestion($priya, $casey, $vase);
        $this->seedFulfillmentThread($noah, $casey, $fulfillment);
        $this->seedAdminSellerSupport($admin, $priya);
        $this->seedAdminCustomerSupport($admin, $casey);
    }

    /**
     * A shopper's question, the seller's answer, and a thank-you — then the
     * answer is published as the listing's one FAQ entry, the same way a
     * seller would click "Publish as FAQ" from the thread.
     */
    private function seedListingQuestion(Seller $priya, Customer $casey, Listing $vase): void
    {
        $conversation = app(OpenConversation::class)(
            ConversationSubject::listingQuestion($priya->id, $casey->id, $vase->id),
            $this->at('2026-08-10 09:00:00'),
        );

        if ($conversation->messages()->exists()) {
            return;
        }

        $postMessage = app(PostMessage::class);
        $markRead = app(MarkConversationRead::class);

        $question = $postMessage($conversation, $casey, MessageBody::of('Does this vase come with a stand for display?'), $this->at('2026-08-10 09:00:00'));
        $markRead($conversation, $priya, $this->at('2026-08-10 13:30:00'));

        $answer = $postMessage($conversation, $priya, MessageBody::of('Yes — it ships with a simple wood stand included.'), $this->at('2026-08-10 14:00:00'));
        $markRead($conversation, $casey, $this->at('2026-08-10 18:00:00'));

        $postMessage($conversation, $casey, MessageBody::of('Wonderful, thank you!'), $this->at('2026-08-11 09:00:00'));

        app(PublishListingFaq::class)(
            $vase,
            FaqDraft::of($question->body, $answer->body),
            $answer,
            $this->at('2026-08-10 14:05:00'),
        );
    }

    /**
     * A shopper checking on a shipment, and the seller's reply — left
     * unread on both sides, the way a thread reads right after it moves.
     */
    private function seedFulfillmentThread(Seller $noah, Customer $casey, Fulfillment $fulfillment): void
    {
        $conversation = app(OpenConversation::class)(
            ConversationSubject::fulfillment($noah->id, $casey->id, $fulfillment->id),
            $this->at('2026-08-12 10:00:00'),
        );

        if ($conversation->messages()->exists()) {
            return;
        }

        $postMessage = app(PostMessage::class);

        $postMessage($conversation, $casey, MessageBody::of('Any update on tracking for this order?'), $this->at('2026-08-12 10:00:00'));
        $postMessage($conversation, $noah, MessageBody::of("Shipped via {$fulfillment->carrier}, tracking {$fulfillment->tracking_number}."), $this->at('2026-08-12 15:00:00'));
    }

    private function seedAdminSellerSupport(Admin $admin, Seller $priya): void
    {
        $conversation = app(OpenConversation::class)(
            ConversationSubject::adminSeller($admin->id, $priya->id),
            $this->at('2026-08-15 09:00:00'),
        );

        if ($conversation->messages()->exists()) {
            return;
        }

        $postMessage = app(PostMessage::class);
        $markRead = app(MarkConversationRead::class);

        $postMessage($conversation, $priya, MessageBody::of("Can you confirm this week's payout will include the delivered oak sculpture order?"), $this->at('2026-08-15 09:00:00'));
        $markRead($conversation, $admin, $this->at('2026-08-15 09:30:00'));

        $postMessage($conversation, $admin, MessageBody::of("Confirmed — it's in this week's run."), $this->at('2026-08-15 10:00:00'));
        $postMessage($conversation, $priya, MessageBody::of('Great, thanks for confirming.'), $this->at('2026-08-15 10:30:00'));
    }

    private function seedAdminCustomerSupport(Admin $admin, Customer $casey): void
    {
        $conversation = app(OpenConversation::class)(
            ConversationSubject::adminCustomer($admin->id, $casey->id),
            $this->at('2026-08-16 09:00:00'),
        );

        if ($conversation->messages()->exists()) {
            return;
        }

        $postMessage = app(PostMessage::class);
        $markRead = app(MarkConversationRead::class);

        $postMessage($conversation, $casey, MessageBody::of('I never received a confirmation email for my last order — can you check?'), $this->at('2026-08-16 09:00:00'));
        $markRead($conversation, $admin, $this->at('2026-08-16 09:15:00'));

        $postMessage($conversation, $admin, MessageBody::of('I see the order marked delivered — happy to resend the confirmation email.'), $this->at('2026-08-16 09:45:00'));
        $postMessage($conversation, $casey, MessageBody::of('That would be great, thank you.'), $this->at('2026-08-16 10:00:00'));
    }

    private function at(string $when): DateTimeImmutable
    {
        return new DateTimeImmutable($when);
    }
}

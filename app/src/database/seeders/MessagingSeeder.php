<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Messaging\MarkConversationRead;
use App\Actions\Messaging\OpenConversation;
use App\Actions\Messaging\OpenThread;
use App\Actions\Messaging\PostMessage;
use App\Actions\Messaging\PublishListingFaq;
use App\Actions\Messaging\ResolveConversation;
use App\Domain\Messaging\ConversationSubject;
use App\Domain\Messaging\FaqDraft;
use App\Domain\Messaging\MessageBody;
use App\Domain\Messaging\ThreadOpening;
use App\Domain\Messaging\ThreadTitle;
use App\Models\Admin;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Fulfillment;
use App\Models\Listing;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Seller;
use DateTimeImmutable;
use Illuminate\Database\Seeder;

/**
 * One thread of each shape, so every messaging inbox in the demo has
 * something on it: a listing question answered and lifted into the
 * storefront's one published FAQ, a second left unanswered, a thread on a
 * shipped fulfillment, a resolved seller support thread, and an open
 * customer support thread tied to an order. Runs last, after `AdminSeeder`
 * (the support threads need an admin to reply), `CustomerSeeder` (Luna's the
 * second asker), and `OrderHistorySeeder` (the fulfillment and the customer
 * support threads each need a real order).
 */
class MessagingSeeder extends Seeder
{
    public const FAQ_LISTING_TITLE = 'Divination Tower Vase, Tall';

    private const FULFILLMENT_LISTING_TITLE = 'Gryffindor Common Room, Late Morning';

    private const UNANSWERED_LISTING_TITLE = 'Burrow Kitchen Tea Bowl';

    public function run(): void
    {
        $admin = Admin::where('email', AdminSeeder::ADMINS[0]['email'])->firstOrFail();
        $sybill = Seller::where('email', SellerSeeder::SYBILL_EMAIL)->firstOrFail();
        $molly = Seller::where('email', SellerSeeder::MOLLY_EMAIL)->firstOrFail();
        $dean = Seller::where('email', SellerSeeder::DEAN_EMAIL)->firstOrFail();
        $hermione = Customer::where('email', CustomerSeeder::HERMIONE_EMAIL)->firstOrFail();
        $luna = Customer::where('email', CustomerSeeder::LUNA_EMAIL)->firstOrFail();
        $vase = Listing::where('title', self::FAQ_LISTING_TITLE)->firstOrFail();
        $teaBowl = Listing::where('title', self::UNANSWERED_LISTING_TITLE)->firstOrFail();

        $fulfillmentOrderItem = OrderItem::where('title', self::FULFILLMENT_LISTING_TITLE)->firstOrFail();
        $fulfillment = Fulfillment::where('order_id', $fulfillmentOrderItem->order_id)
            ->where('seller_id', $dean->id)
            ->firstOrFail();
        $teaBowlOrder = Order::findOrFail(OrderItem::where('title', self::UNANSWERED_LISTING_TITLE)->firstOrFail()->order_id);

        $this->seedAnsweredListingQuestion($sybill, $hermione, $vase);
        $this->seedUnansweredListingQuestion($molly, $luna, $teaBowl);
        $this->seedFulfillmentThread($dean, $hermione, $fulfillment);
        $this->seedResolvedSellerSupportThread($admin, $sybill);
        $this->seedOpenCustomerSupportThread($admin, $hermione, $teaBowlOrder);
    }

    /**
     * A shopper's question, the seller's answer — one message replying to
     * another — and a thank-you, then the answer is published as the
     * listing's one FAQ entry, which resolves the thread the same way a
     * seller's own "Publish as FAQ" click does.
     */
    private function seedAnsweredListingQuestion(Seller $sybill, Customer $hermione, Listing $vase): void
    {
        if ($vase->faqs()->exists()) {
            return;
        }

        $openThread = app(OpenThread::class);
        $postMessage = app(PostMessage::class);
        $markRead = app(MarkConversationRead::class);

        $question = MessageBody::of('Does this vase come with a stand for display?');
        $conversation = $openThread(
            ThreadOpening::listingQuestion($sybill->id, $hermione->id, $vase->id, ThreadTitle::fromBody($question->value)),
            $hermione,
            $question,
            $this->at('2026-08-10 09:00:00'),
        );
        $questionMessage = $conversation->messages()->sole();
        $markRead($conversation, $sybill, $this->at('2026-08-10 13:30:00'));

        $answer = $postMessage($conversation, $sybill, MessageBody::of('Yes — it ships with a simple wood stand included.'), $this->at('2026-08-10 14:00:00'));
        $markRead($conversation, $hermione, $this->at('2026-08-10 18:00:00'));

        $postMessage($conversation, $hermione, MessageBody::of('Wonderful, thank you!'), $this->at('2026-08-11 09:00:00'), $answer);

        app(PublishListingFaq::class)(
            $vase,
            FaqDraft::of($questionMessage->body, $answer->body),
            $answer,
            $this->at('2026-08-10 14:05:00'),
        );
    }

    /**
     * A second shopper's question the seller has not gotten to yet — the
     * seller's questions queue reads this one as unanswered, ahead of any
     * thread the seller has already replied to.
     */
    private function seedUnansweredListingQuestion(Seller $molly, Customer $luna, Listing $teaBowl): void
    {
        $openThread = app(OpenThread::class);
        $question = MessageBody::of('Is this microwave-safe?');

        if (Conversation::query()->where('listing_id', $teaBowl->id)->where('customer_id', $luna->id)->exists()) {
            return;
        }

        $openThread(
            ThreadOpening::listingQuestion($molly->id, $luna->id, $teaBowl->id, ThreadTitle::fromBody($question->value)),
            $luna,
            $question,
            $this->at('2026-08-13 09:00:00'),
        );
    }

    /**
     * A shopper checking on a shipment, and the seller's reply — left
     * unread on both sides, the way a thread reads right after it moves.
     */
    private function seedFulfillmentThread(Seller $dean, Customer $hermione, Fulfillment $fulfillment): void
    {
        $conversation = app(OpenConversation::class)(
            ConversationSubject::fulfillment($dean->id, $hermione->id, $fulfillment->id),
            $this->at('2026-08-12 10:00:00'),
        );

        if ($conversation->messages()->exists()) {
            return;
        }

        $postMessage = app(PostMessage::class);

        $postMessage($conversation, $hermione, MessageBody::of('Any update on tracking for this order?'), $this->at('2026-08-12 10:00:00'));
        $postMessage($conversation, $dean, MessageBody::of("Shipped via {$fulfillment->carrier}, tracking {$fulfillment->tracking_number}."), $this->at('2026-08-12 15:00:00'));
    }

    /**
     * A seller's payout question, answered and marked resolved — the desk's
     * queue reads a resolved thread as done, and the seller's own inbox
     * shows the title on this thread.
     */
    private function seedResolvedSellerSupportThread(Admin $admin, Seller $sybill): void
    {
        $title = ThreadTitle::of('Payout timing');

        if ($sybill->conversations()->where('title', $title->value)->exists()) {
            return;
        }

        $openThread = app(OpenThread::class);
        $postMessage = app(PostMessage::class);
        $markRead = app(MarkConversationRead::class);

        $conversation = $openThread(
            ThreadOpening::adminSeller($sybill->id, $title),
            $sybill,
            MessageBody::of("Can you confirm this week's payout will include the delivered oak sculpture order?"),
            $this->at('2026-08-15 09:00:00'),
        );
        $markRead($conversation, $admin, $this->at('2026-08-15 09:30:00'));

        $postMessage($conversation, $admin, MessageBody::of("Confirmed — it's in this week's run."), $this->at('2026-08-15 10:00:00'));
        $postMessage($conversation, $sybill, MessageBody::of('Great, thanks for confirming.'), $this->at('2026-08-15 10:30:00'));
        $markRead($conversation, $admin, $this->at('2026-08-15 10:35:00'));

        app(ResolveConversation::class)($conversation, $admin, $this->at('2026-08-15 10:40:00'));
    }

    /**
     * A customer's question about a specific order, left open — the desk's
     * needs-reply queue reads this one as waiting.
     */
    private function seedOpenCustomerSupportThread(Admin $admin, Customer $hermione, Order $order): void
    {
        $title = ThreadTitle::of('Missing confirmation email');

        if ($hermione->conversations()->where('title', $title->value)->exists()) {
            return;
        }

        $openThread = app(OpenThread::class);

        $openThread(
            ThreadOpening::adminCustomer($hermione->id, $title, $order->id),
            $hermione,
            MessageBody::of('I never received a confirmation email for this order — can you check?'),
            $this->at('2026-08-16 09:00:00'),
        );
    }

    private function at(string $when): DateTimeImmutable
    {
        return new DateTimeImmutable($when);
    }
}

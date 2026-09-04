<?php

declare(strict_types=1);

namespace App\Seller;

use App\Domain\Auth\ActorType;
use App\Domain\Seller\ActivityKind;
use App\Domain\Seller\FeedEvent;
use App\Domain\Seller\FeedIcon;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;

/**
 * The words the two of them exchanged. An order scope keeps to the threads
 * about that parcel or about the pieces on it; a customer scope takes every
 * thread the two of them share.
 */
final readonly class MessagingSource implements ActivityFeedSource
{
    private const string UNTITLED = 'the order';

    /**
     * @return list<FeedEvent>
     */
    public function events(FeedScope $scope): array
    {
        $events = [];

        foreach ($this->conversations($scope) as $conversation) {
            foreach ($conversation->messages as $message) {
                $events[] = $this->toFeedEvent($message, $conversation, $scope);
            }
        }

        return $events;
    }

    /**
     * @return list<Conversation>
     */
    private function conversations(FeedScope $scope): array
    {
        return array_values(Conversation::query()
            ->where('seller_id', $scope->sellerId)
            ->where('customer_id', $scope->customerId)
            ->when($scope->isOneOrder, fn (Builder $query): Builder => $query->where(
                fn (BuilderContract $about): BuilderContract => $about
                    ->whereIn('fulfillment_id', $scope->fulfillmentIds)
                    ->orWhereIn('listing_id', $scope->listingIds),
            ))
            ->with('messages')
            ->get()
            ->all());
    }

    private function toFeedEvent(Message $message, Conversation $conversation, FeedScope $scope): FeedEvent
    {
        $isSeller = $message->sender_type === ActorType::Seller->value;
        $title = $conversation->title ?? self::UNTITLED;

        return new FeedEvent(
            occurredAt: $message->sent_at->toDateTimeImmutable(),
            kind: ActivityKind::Messages,
            icon: FeedIcon::Chat,
            actor: $isSeller ? 'You' : $scope->customerName,
            text: ($isSeller ? 'replied in' : 'wrote in')." \u{201C}{$title}\u{201D}",
            quote: $message->body,
            link: route('seller.messages.show', $conversation->id),
        );
    }
}

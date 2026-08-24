<?php

declare(strict_types=1);

namespace App\Logging;

/**
 * The event vocabulary of docs/alignment.md §2.3, holding the cases this
 * prototype's features support. `<subject>.<verb>` in the imperative; the
 * phase field carries the tense.
 *
 * The events waiting on features this prototype has yet to grow —
 * `order.cancel`, `order.sweep`, `fulfillment.decline`, `refund.issue`,
 * `moderation.remove_listing`, `moderation.lift_listing_removal`,
 * `rate_limit.exceed` — join the enum with the work that emits them.
 */
enum StoryEvent: string
{
    case HttpRequest = 'http.request';
    case MagicLinkRequest = 'magic_link.request';
    case MagicLinkConsume = 'magic_link.consume';
    case CustomerMerge = 'customer.merge';
    case ListingCreate = 'listing.create';
    case ListingUpdate = 'listing.update';
    case ListingPublish = 'listing.publish';
    case ListingTransition = 'listing.transition';
    case ListingView = 'listing.view';
    case CartAdd = 'cart.add';
    case CartUpdate = 'cart.update';
    case CartRemove = 'cart.remove';
    case OrderPlace = 'order.place';
    case OrderPay = 'order.pay';
    case FulfillmentShip = 'fulfillment.ship';
    case FulfillmentDeliver = 'fulfillment.deliver';
    case LedgerWrite = 'ledger.write';
    case PayoutRun = 'payout.run';
    case PayoutPay = 'payout.pay';
    case ConversationOpen = 'conversation.open';
    case MessagePost = 'message.post';
    case FaqPublish = 'faq.publish';
    case FaqUnpublish = 'faq.unpublish';
    case NotificationWrite = 'notification.write';
    case NotificationDeliver = 'notification.deliver';
    case ModerationBlockCustomer = 'moderation.block_customer';
    case ModerationLiftCustomerBlock = 'moderation.lift_customer_block';
    case MigrateRun = 'migrate.run';
    case MigrateApply = 'migrate.apply';
    case SeedRun = 'seed.run';
    case AppBoot = 'app.boot';
    case AppShutdown = 'app.shutdown';

    /**
     * A request is not a unit of work: the actions inside it are, and each
     * one mints its own id when it opens. Every other event that opens with
     * `will` is a unit of work.
     */
    public function opensUnitOfWork(): bool
    {
        return $this !== self::HttpRequest;
    }

    /**
     * Every ledger entry is written, so the money trail is a debug stream
     * under the story rather than part of it.
     */
    public function level(): StoryLevel
    {
        return $this === self::LedgerWrite ? StoryLevel::Debug : StoryLevel::Info;
    }

    /**
     * A refusal is `info`: a rule held, and the reader is meant to see it.
     * The one exception is the listing-view collapse, which refuses every
     * view after the first in an hour and would drown the stream at `info`.
     */
    public function refusalLevel(): StoryLevel
    {
        return $this === self::ListingView ? StoryLevel::Debug : StoryLevel::Info;
    }
}

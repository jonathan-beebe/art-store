<?php

declare(strict_types=1);

namespace App\Logging;

/**
 * The event vocabulary of docs/spec.md §2.3, holding the cases the
 * app's features support. `<subject>.<verb>` in the imperative; the
 * phase field carries the tense.
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
    case OrderCancel = 'order.cancel';
    case OrderSweep = 'order.sweep';
    case FulfillmentShip = 'fulfillment.ship';
    case FulfillmentDeliver = 'fulfillment.deliver';
    case FulfillmentDecline = 'fulfillment.decline';
    case RefundIssue = 'refund.issue';
    case LedgerWrite = 'ledger.write';
    case PayoutRun = 'payout.run';
    case PayoutPay = 'payout.pay';
    case ConversationOpen = 'conversation.open';
    case ConversationResolve = 'conversation.resolve';
    case ConversationReopen = 'conversation.reopen';
    case MessagePost = 'message.post';
    case FaqPublish = 'faq.publish';
    case FaqUnpublish = 'faq.unpublish';
    case NotificationWrite = 'notification.write';
    case NotificationDeliver = 'notification.deliver';
    case ModerationRemoveListing = 'moderation.remove_listing';
    case ModerationLiftListingRemoval = 'moderation.lift_listing_removal';
    case ModerationBlockCustomer = 'moderation.block_customer';
    case ModerationLiftCustomerBlock = 'moderation.lift_customer_block';
    case RateLimitExceed = 'rate_limit.exceed';
    case QueryExceed = 'query.exceed';
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
     * under the story rather than part of it. A slow query is the one `did`
     * line an operator wants paged on, so it is `warn`.
     */
    public function level(): StoryLevel
    {
        return match ($this) {
            self::LedgerWrite => StoryLevel::Debug,
            self::QueryExceed => StoryLevel::Warn,
            default => StoryLevel::Info,
        };
    }

    /**
     * A refusal is `info`: a rule held, and the reader is meant to see it.
     * The listing-view collapse refuses every view after the first in an
     * hour and would drown the stream at `info`, so it is `debug` instead. A
     * rate-limit trip is the one refusal an operator wants paged on, so it
     * is `warn`.
     */
    public function refusalLevel(): StoryLevel
    {
        return match ($this) {
            self::ListingView => StoryLevel::Debug,
            self::RateLimitExceed => StoryLevel::Warn,
            default => StoryLevel::Info,
        };
    }
}

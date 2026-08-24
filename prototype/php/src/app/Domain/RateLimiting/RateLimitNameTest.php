<?php

declare(strict_types=1);

namespace App\Domain\RateLimiting;

it('names its own env variable per limit', function (RateLimitName $limit, string $variable): void {
    expect($limit->envVariable())->toBe($variable);
})->with([
    'magic_link_request' => [RateLimitName::MagicLinkRequest, 'RATE_LIMIT_MAGIC_LINK_REQUEST'],
    'magic_link_consume' => [RateLimitName::MagicLinkConsume, 'RATE_LIMIT_MAGIC_LINK_CONSUME'],
    'message_post' => [RateLimitName::MessagePost, 'RATE_LIMIT_MESSAGE_POST'],
    'conversation_open' => [RateLimitName::ConversationOpen, 'RATE_LIMIT_CONVERSATION_OPEN'],
    'checkout' => [RateLimitName::Checkout, 'RATE_LIMIT_CHECKOUT'],
    'payment_attempt' => [RateLimitName::PaymentAttempt, 'RATE_LIMIT_PAYMENT_ATTEMPT'],
    'listing_write' => [RateLimitName::ListingWrite, 'RATE_LIMIT_LISTING_WRITE'],
]);

it('holds the default docs/alignment.md §3 names for every limit when unset', function (RateLimitName $limit, string $default): void {
    expect($limit->default())->toBe($default);
})->with([
    'magic_link_request' => [RateLimitName::MagicLinkRequest, '5/15m'],
    'magic_link_consume' => [RateLimitName::MagicLinkConsume, '20/15m'],
    'message_post' => [RateLimitName::MessagePost, '30/1h'],
    'conversation_open' => [RateLimitName::ConversationOpen, '10/1h'],
    'checkout' => [RateLimitName::Checkout, '10/1h'],
    'payment_attempt' => [RateLimitName::PaymentAttempt, '5/15m'],
    'listing_write' => [RateLimitName::ListingWrite, '60/1h'],
]);

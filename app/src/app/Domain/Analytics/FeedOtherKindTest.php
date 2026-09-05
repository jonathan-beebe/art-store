<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

it('answers whether a kind is a listing', function (FeedOtherKind $kind, bool $isListing): void {
    expect($kind->isListing())->toBe($isListing);
})->with([
    'actor' => [FeedOtherKind::Actor, false],
    'listing' => [FeedOtherKind::Listing, true],
    'order' => [FeedOtherKind::Order, false],
    'cart' => [FeedOtherKind::Cart, false],
    'store' => [FeedOtherKind::Store, false],
    'help article' => [FeedOtherKind::HelpArticle, false],
]);

it('answers whether a kind is a help article', function (FeedOtherKind $kind, bool $isHelpArticle): void {
    expect($kind->isHelpArticle())->toBe($isHelpArticle);
})->with([
    'actor' => [FeedOtherKind::Actor, false],
    'listing' => [FeedOtherKind::Listing, false],
    'order' => [FeedOtherKind::Order, false],
    'cart' => [FeedOtherKind::Cart, false],
    'store' => [FeedOtherKind::Store, false],
    'help article' => [FeedOtherKind::HelpArticle, true],
]);

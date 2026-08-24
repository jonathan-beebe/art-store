<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

it('reads a portal prefix as its own site', function (string $pattern, PageViewSite $site): void {
    expect(PageViewSite::fromRoutePattern($pattern))->toBe($site);
})->with([
    'the seller portal root' => ['/seller', PageViewSite::Seller],
    'a seller portal page' => ['/seller/listings/{listing}', PageViewSite::Seller],
    'the admin root' => ['/admin', PageViewSite::Admin],
    'an admin page' => ['/admin/orders/{order}', PageViewSite::Admin],
]);

it('reads everything else as the storefront', function (string $pattern): void {
    expect(PageViewSite::fromRoutePattern($pattern))->toBe(PageViewSite::Shop);
})->with([
    'the root' => ['/'],
    'a listing page' => ['/art/{listing}'],
    'a magic link' => ['/auth/magic/{token}'],
]);

it('does not let a path merely starting with a prefix\'s letters claim the portal', function (string $pattern): void {
    expect(PageViewSite::fromRoutePattern($pattern))->toBe(PageViewSite::Shop);
})->with([
    'sellers-guide' => ['/sellers-guide'],
    'administration' => ['/administration'],
]);

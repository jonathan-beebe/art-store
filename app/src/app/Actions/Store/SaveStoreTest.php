<?php

declare(strict_types=1);

use App\Actions\Store\SaveStore;
use App\Actions\Store\StartStore;
use App\Domain\Store\StoreDraft;
use App\Domain\Store\StoreLinkKind;
use App\Domain\Store\StoreVisibility;
use App\Models\Seller;
use App\Models\StoreProfile;
use Tests\CapturedStory;

$draft = function (StoreVisibility $visibility = StoreVisibility::Hidden, array $links = [], string $slug = 'the-burrow-craftworks'): StoreDraft {
    /** @var array<string, string> $links */
    return StoreDraft::of('Burrow Works', $slug, 'Made by the fire', 'Devon', $visibility, $links);
};

$store = fn (): StoreProfile => app(StartStore::class)(
    Seller::factory()->create(['shop_name' => 'The Burrow Craftworks']),
);

$now = new DateTimeImmutable('2026-09-03 10:00:00');

it('writes the identity the draft carries', function () use ($store, $draft, $now): void {
    $profile = $store();

    app(SaveStore::class)($profile, $draft(), $now);

    $saved = $profile->fresh();
    expect($saved?->name)->toBe('Burrow Works')
        ->and($saved?->tagline)->toBe('Made by the fire')
        ->and($saved?->location)->toBe('Devon');
});

it('stamps a store published for the first time with the moment it opened', function () use ($store, $draft, $now): void {
    $profile = $store();

    app(SaveStore::class)($profile, $draft(StoreVisibility::Published), $now);

    expect($profile->fresh()?->published_at?->toDateTimeString())->toBe('2026-09-03 10:00:00');
});

it('clears the stamp when the store is hidden', function () use ($store, $draft, $now): void {
    $profile = $store();
    app(SaveStore::class)($profile, $draft(StoreVisibility::Published), $now);

    app(SaveStore::class)($profile, $draft(StoreVisibility::Hidden), $now);

    expect($profile->fresh()?->published_at)->toBeNull();
});

it('keeps the day a store first opened across a later save', function () use ($store, $draft, $now): void {
    $profile = $store();
    app(SaveStore::class)($profile, $draft(StoreVisibility::Published), $now);

    app(SaveStore::class)($profile, $draft(StoreVisibility::Published), new DateTimeImmutable('2026-10-01 10:00:00'));

    expect($profile->fresh()?->published_at?->toDateTimeString())->toBe('2026-09-03 10:00:00');
});

it('moves the store when the draft names a new address', function () use ($store, $draft, $now): void {
    $profile = $store();

    app(SaveStore::class)($profile, $draft(slug: 'burrow-works'), $now);

    expect($profile->fresh()?->slug)->toBe('burrow-works')
        ->and($profile->slugs()->retired()->pluck('slug')->all())->toBe(['the-burrow-craftworks']);
});

it('gives each kind the seller filled in a row, in case order', function () use ($store, $draft, $now): void {
    $profile = $store();

    app(SaveStore::class)($profile, $draft(links: [
        StoreLinkKind::Instagram->value => '@theburrowcraftworks',
        StoreLinkKind::Website->value => 'https://theburrow.example',
    ]), $now);

    expect($profile->links()->pluck('kind')->all())->toBe([StoreLinkKind::Website, StoreLinkKind::Instagram])
        ->and($profile->links()->pluck('position')->all())->toBe([0, 1]);
});

it('drops the row for a kind the seller cleared', function () use ($store, $draft, $now): void {
    $profile = $store();
    app(SaveStore::class)($profile, $draft(links: [
        StoreLinkKind::Website->value => 'https://theburrow.example',
        StoreLinkKind::Instagram->value => '@theburrowcraftworks',
    ]), $now);

    app(SaveStore::class)($profile, $draft(links: [
        StoreLinkKind::Website->value => 'https://theburrow.example',
    ]), $now);

    expect($profile->links()->pluck('kind')->all())->toBe([StoreLinkKind::Website]);
});

it('keeps one row for a link the seller changed', function () use ($store, $draft, $now): void {
    $profile = $store();
    app(SaveStore::class)($profile, $draft(links: [StoreLinkKind::Website->value => 'https://old.example']), $now);

    app(SaveStore::class)($profile, $draft(links: [StoreLinkKind::Website->value => 'https://new.example']), $now);

    expect($profile->links()->count())->toBe(1)
        ->and($profile->links()->sole()->url)->toBe('https://new.example');
});

it('tells the story of the save', function () use ($store, $draft, $now): void {
    $profile = $store();
    $log = CapturedStory::capture();

    app(SaveStore::class)($profile, $draft(), $now);

    expect($log->values('phase', 'store.save'))->toBe(['will', 'did'])
        ->and($log->line('store.save', 'did')['data'])->toMatchArray([
            'seller_id' => $profile->seller_id,
            'store_profile_id' => $profile->id,
        ]);
});

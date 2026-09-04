<?php

declare(strict_types=1);

use App\Actions\Store\MoveStoreSection;
use App\Domain\Store\StoreSectionMove;
use App\Models\StoreProfile;
use App\Models\StoreSection;

/**
 * A store page of two sections, a story then a gallery.
 *
 * @return array{StoreProfile, StoreSection, StoreSection}
 */
$page = function (): array {
    $profile = StoreProfile::factory()->create();

    return [
        $profile,
        StoreSection::factory()->create(['store_profile_id' => $profile->id, 'position' => 0]),
        StoreSection::factory()->gallery()->create(['store_profile_id' => $profile->id, 'position' => 1]),
    ];
};

it('swaps a section with the one above it', function () use ($page): void {
    [$profile, $first, $second] = $page();

    app(MoveStoreSection::class)($second, StoreSectionMove::Up);

    expect($profile->sections()->pluck('id')->all())->toBe([$second->id, $first->id]);
});

it('swaps a section with the one below it', function () use ($page): void {
    [$profile, $first, $second] = $page();

    app(MoveStoreSection::class)($first, StoreSectionMove::Down);

    expect($profile->sections()->pluck('id')->all())->toBe([$second->id, $first->id]);
});

it('leaves the order alone at the top', function () use ($page): void {
    [$profile, $first, $second] = $page();

    app(MoveStoreSection::class)($first, StoreSectionMove::Up);

    expect($profile->sections()->pluck('id')->all())->toBe([$first->id, $second->id]);
});

it('leaves the order alone at the bottom', function () use ($page): void {
    [$profile, $first, $second] = $page();

    app(MoveStoreSection::class)($second, StoreSectionMove::Down);

    expect($profile->sections()->pluck('id')->all())->toBe([$first->id, $second->id]);
});

it('ignores another store\'s sections when it looks for a neighbor', function () use ($page): void {
    [$profile, $first, $second] = $page();
    StoreSection::factory()->create(['store_profile_id' => StoreProfile::factory()->create()->id, 'position' => 0]);

    app(MoveStoreSection::class)($first, StoreSectionMove::Up);

    expect($profile->sections()->pluck('id')->all())->toBe([$first->id, $second->id]);
});

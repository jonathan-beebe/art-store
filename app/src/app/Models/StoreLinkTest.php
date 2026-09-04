<?php

declare(strict_types=1);

use App\Domain\Store\StoreLinkKind;
use App\Models\StoreLink;
use App\Models\StoreProfile;

it('mints an slk_ id', function (): void {
    expect(StoreLink::factory()->create()->id)->toStartWith('slk_');
});

it('reads its kind back as the domain enum', function (): void {
    expect(StoreLink::factory()->create()->kind)->toBe(StoreLinkKind::Website)
        ->and(StoreLink::factory()->instagram()->create()->kind)->toBe(StoreLinkKind::Instagram);
});

it('belongs to the store it points away from', function (): void {
    $profile = StoreProfile::factory()->create();

    $link = StoreLink::factory()->create(['store_profile_id' => $profile->id]);

    expect($link->storeProfile?->id)->toBe($profile->id);
});

it('holds one link per kind on a store', function (): void {
    $profile = StoreProfile::factory()->create();
    StoreLink::factory()->create(['store_profile_id' => $profile->id]);

    expect(fn () => StoreLink::factory()->create(['store_profile_id' => $profile->id, 'position' => 1]))
        ->toThrow(Illuminate\Database\UniqueConstraintViolationException::class);
});

it('sends a website link where the seller typed it', function (): void {
    $link = StoreLink::factory()->create(['url' => 'https://theburrow.example']);

    expect($link->href())->toBe('https://theburrow.example')
        ->and($link->display())->toBe('theburrow.example');
});

it('turns an Instagram handle into a profile address', function (string $stored): void {
    $link = StoreLink::factory()->instagram()->create(['url' => $stored]);

    expect($link->href())->toBe('https://instagram.com/theburrowcraftworks')
        ->and($link->display())->toBe('@theburrowcraftworks');
})->with([
    'with the sigil' => '@theburrowcraftworks',
    'without it' => 'theburrowcraftworks',
]);

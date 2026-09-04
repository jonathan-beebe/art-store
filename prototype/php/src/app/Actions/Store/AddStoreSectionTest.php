<?php

declare(strict_types=1);

use App\Actions\Store\AddStoreSection;
use App\Domain\Store\StoreSectionKind;
use App\Models\StoreProfile;

it('puts the first section at the head of the page', function (): void {
    $profile = StoreProfile::factory()->create();

    $section = app(AddStoreSection::class)($profile, StoreSectionKind::Story);

    expect($section->position)->toBe(0)
        ->and($section->kind)->toBe(StoreSectionKind::Story)
        ->and($section->store_profile_id)->toBe($profile->id);
});

it('appends one place past the section already running highest', function (): void {
    $profile = StoreProfile::factory()->create();
    app(AddStoreSection::class)($profile, StoreSectionKind::Story);

    $second = app(AddStoreSection::class)($profile, StoreSectionKind::Gallery);

    expect($second->position)->toBe(1)
        ->and($second->kind)->toBe(StoreSectionKind::Gallery);
});

it('counts only the sections of its own store', function (): void {
    app(AddStoreSection::class)(StoreProfile::factory()->create(), StoreSectionKind::Story);

    $section = app(AddStoreSection::class)(StoreProfile::factory()->create(), StoreSectionKind::Story);

    expect($section->position)->toBe(0);
});

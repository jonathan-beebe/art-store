<?php

declare(strict_types=1);

namespace App\Domain\Store;

it('carries what the seller typed', function (): void {
    $draft = StoreDraft::of(
        'The Burrow Craftworks',
        'the-burrow-craftworks',
        'Knitted, thrown, and carved at the Burrow',
        'Ottery St Catchpole, Devon',
        StoreVisibility::Published,
        [StoreLinkKind::Website->value => 'https://theburrow.example'],
    );

    expect($draft->name)->toBe('The Burrow Craftworks')
        ->and($draft->slug)->toBe('the-burrow-craftworks')
        ->and($draft->visibility)->toBe(StoreVisibility::Published);
});

it('hands over the profile columns it owns', function (): void {
    $draft = StoreDraft::of('Nine Owls', 'nine-owls', null, null, StoreVisibility::Hidden);

    expect($draft->attributes())->toBe([
        'name' => 'Nine Owls',
        'tagline' => null,
        'location' => null,
    ]);
});

it('orders the links the way the kinds are declared', function (): void {
    $draft = StoreDraft::of('Nine Owls', 'nine-owls', null, null, StoreVisibility::Hidden, [
        StoreLinkKind::Instagram->value => '@nineowls',
        StoreLinkKind::Website->value => 'https://nineowls.example',
    ]);

    expect($draft->orderedLinks())->toBe([
        ['kind' => StoreLinkKind::Website, 'url' => 'https://nineowls.example', 'position' => 0],
        ['kind' => StoreLinkKind::Instagram, 'url' => '@nineowls', 'position' => 1],
    ]);
});

it('leaves out a kind the seller left blank', function (): void {
    $draft = StoreDraft::of('Nine Owls', 'nine-owls', null, null, StoreVisibility::Hidden, [
        StoreLinkKind::Instagram->value => '@nineowls',
    ]);

    expect($draft->orderedLinks())->toBe([
        ['kind' => StoreLinkKind::Instagram, 'url' => '@nineowls', 'position' => 1],
    ]);
});

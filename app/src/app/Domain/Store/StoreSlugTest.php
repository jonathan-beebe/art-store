<?php

declare(strict_types=1);

namespace App\Domain\Store;

it('accepts an address of lowercase letters, digits, and single hyphens', function (string $slug): void {
    expect(StoreSlug::isValid($slug))->toBeTrue();
})->with([
    'the-burrow-craftworks',
    'luna',
    'studio-9',
    str_repeat('a', StoreSlug::MAX_LENGTH),
]);

it('refuses an address outside the shape or the length', function (string $slug): void {
    expect(StoreSlug::isValid($slug))->toBeFalse();
})->with([
    'the empty string' => '',
    'two characters' => 'ab',
    'one past the ceiling' => str_repeat('a', StoreSlug::MAX_LENGTH + 1),
    'uppercase' => 'The-Burrow',
    'a leading hyphen' => '-burrow',
    'a trailing hyphen' => 'burrow-',
    'a double hyphen' => 'burrow--craftworks',
    'a space' => 'the burrow',
    'an underscore' => 'the_burrow',
    'a slash' => 'the/burrow',
]);

it('reads an address off a store name', function (string $name, string $expected): void {
    expect(StoreSlug::fromName($name))->toBe($expected);
})->with([
    'a plain name' => ['The Burrow Craftworks', 'the-burrow-craftworks'],
    'punctuation' => ["Trelawney's Tower Studio", 'trelawney-s-tower-studio'],
    'an accent' => ['Café Noir', 'cafe-noir'],
    'runs of separators' => ['  Nine  —  Owls  ', 'nine-owls'],
    'a name that transliterates to nothing' => ['???', 'store'],
    'a name shorter than the floor' => ['Jo', 'jo-store'],
]);

it('gives a name past the ceiling an address that fits it', function (): void {
    $slug = StoreSlug::fromName(str_repeat('pottery ', 20));

    expect(strlen($slug))->toBeLessThanOrEqual(StoreSlug::MAX_LENGTH)
        ->and(StoreSlug::isValid($slug))->toBeTrue();
});

it('hands back the address a name asks for when nothing holds it', function (): void {
    expect(StoreSlug::firstFree('The Burrow Craftworks', ['nine-owls']))->toBe('the-burrow-craftworks');
});

it('counts up past every address already taken', function (): void {
    expect(StoreSlug::firstFree('Luna', ['luna', 'luna-2', 'luna-3']))->toBe('luna-4');
});

it('keeps a counted-up address inside the ceiling', function (): void {
    $base = StoreSlug::fromName(str_repeat('pottery ', 20));

    $free = StoreSlug::firstFree(str_repeat('pottery ', 20), [$base]);

    expect(strlen($free))->toBeLessThanOrEqual(StoreSlug::MAX_LENGTH)
        ->and(StoreSlug::isValid($free))->toBeTrue()
        ->and($free)->not->toBe($base);
});

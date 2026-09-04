<?php

declare(strict_types=1);

namespace App\Domain\Store;

it('names the two states a store page answers in', function (): void {
    expect(array_column(StoreVisibility::cases(), 'value'))->toBe(['published', 'hidden']);
});

it('labels every state and says which one is public', function (StoreVisibility $visibility, string $label, bool $published): void {
    expect($visibility->label())->toBe($label)
        ->and($visibility->isPublished())->toBe($published);
})->with([
    'published' => [StoreVisibility::Published, 'Published', true],
    'hidden' => [StoreVisibility::Hidden, 'Hidden', false],
]);

<?php

declare(strict_types=1);

namespace App\Domain\Store;

it('names the places a store points buyers to', function (): void {
    expect(array_column(StoreLinkKind::cases(), 'value'))->toBe(['website', 'instagram']);
});

it('labels every kind and gives it a placeholder', function (StoreLinkKind $kind, string $label): void {
    expect($kind->label())->toBe($label)
        ->and($kind->placeholder())->not->toBe('');
})->with([
    'website' => [StoreLinkKind::Website, 'Website'],
    'instagram' => [StoreLinkKind::Instagram, 'Instagram'],
]);

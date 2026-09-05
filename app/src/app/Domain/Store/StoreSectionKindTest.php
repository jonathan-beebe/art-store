<?php

declare(strict_types=1);

namespace App\Domain\Store;

it('names the section kinds a store page is built from', function (): void {
    expect(array_column(StoreSectionKind::cases(), 'value'))->toBe(['story', 'gallery']);
});

it('labels and describes every kind', function (StoreSectionKind $kind, string $label): void {
    expect($kind->label())->toBe($label)
        ->and($kind->description())->not->toBe('');
})->with([
    'story' => [StoreSectionKind::Story, 'Story'],
    'gallery' => [StoreSectionKind::Gallery, 'Gallery'],
]);

it('says which fields a kind uses', function (StoreSectionKind $kind, array $fields): void {
    expect($kind->fields())->toBe($fields);
})->with([
    'story' => [StoreSectionKind::Story, [StoreSectionField::Heading, StoreSectionField::Body]],
    'gallery' => [StoreSectionKind::Gallery, [StoreSectionField::Heading, StoreSectionField::Images]],
]);

it('allows the fields it uses and refuses the rest', function (StoreSectionKind $kind, StoreSectionField $field, bool $allowed): void {
    expect($kind->allows($field))->toBe($allowed);
})->with([
    'a story heading' => [StoreSectionKind::Story, StoreSectionField::Heading, true],
    'a story body' => [StoreSectionKind::Story, StoreSectionField::Body, true],
    'story images' => [StoreSectionKind::Story, StoreSectionField::Images, false],
    'a gallery heading' => [StoreSectionKind::Gallery, StoreSectionField::Heading, true],
    'gallery images' => [StoreSectionKind::Gallery, StoreSectionField::Images, true],
    'a gallery body' => [StoreSectionKind::Gallery, StoreSectionField::Body, false],
]);

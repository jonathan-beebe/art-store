<?php

declare(strict_types=1);

namespace App\Domain\Store;

it('names what an uploaded store picture can be for', function (): void {
    expect(array_column(StorePictureRole::cases(), 'value'))->toBe(['portrait', 'cover', 'gallery']);
});

it('labels every role and names the profile column it fills', function (StorePictureRole $role, string $label, ?string $column): void {
    expect($role->label())->toBe($label)
        ->and($role->profileColumn())->toBe($column);
})->with([
    'portrait' => [StorePictureRole::Portrait, 'Portrait', 'portrait_image_id'],
    'cover' => [StorePictureRole::Cover, 'Cover', 'cover_image_id'],
    'gallery' => [StorePictureRole::Gallery, 'Picture', null],
]);

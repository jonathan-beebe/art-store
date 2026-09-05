<?php

declare(strict_types=1);

namespace App\Domain\Text;

it('names a position the way a seller reads it', function (int $position, string $word): void {
    expect(Ordinal::of($position))->toBe($word);
})->with([
    '1st' => [1, '1st'],
    '2nd' => [2, '2nd'],
    '3rd' => [3, '3rd'],
    '4th' => [4, '4th'],
    '11th' => [11, '11th'],
    '12th' => [12, '12th'],
    '13th' => [13, '13th'],
    '21st' => [21, '21st'],
    '22nd' => [22, '22nd'],
    '23rd' => [23, '23rd'],
]);

<?php

declare(strict_types=1);

namespace App\Domain\Store;

it('names the two ways a section moves', function (): void {
    expect(array_column(StoreSectionMove::cases(), 'value'))->toBe(['up', 'down']);
});

it('steps a section one place in the direction it names', function (StoreSectionMove $move, int $offset): void {
    expect($move->offset())->toBe($offset);
})->with([
    'up' => [StoreSectionMove::Up, -1],
    'down' => [StoreSectionMove::Down, 1],
]);

<?php

declare(strict_types=1);

namespace App\Support\Configurator;

it('pads a stored row with blanks, and offers only blank rows for a section with none stored', function (string $method, array $row, array $expected): void {
    expect(DescriptionSectionRows::$method([$row]))->toBe([$row, ...array_fill(0, 3, $expected)])
        ->and(DescriptionSectionRows::$method(null))->toBe(array_fill(0, 3, $expected));
})->with([
    'size chart' => [
        'sizeChart',
        ['label' => 'S', 'value1' => '36 in', 'value2' => '27 in'],
        ['label' => '', 'value1' => '', 'value2' => ''],
    ],
    'specs' => [
        'specs',
        ['label' => 'Material', 'value' => 'Phoenix feather'],
        ['label' => '', 'value' => ''],
    ],
    'faq' => [
        'faq',
        ['question' => 'Does it work on non-magical wood?', 'answer' => 'No.'],
        ['question' => '', 'answer' => ''],
    ],
]);

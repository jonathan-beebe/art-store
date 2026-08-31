<?php

declare(strict_types=1);

namespace App\Support\Configurator;

it('pads existing size-chart rows with blanks', function (): void {
    expect(DescriptionSectionRows::sizeChart([
        ['label' => 'S', 'value1' => '36 in', 'value2' => '27 in'],
    ]))->toBe([
        ['label' => 'S', 'value1' => '36 in', 'value2' => '27 in'],
        ['label' => '', 'value1' => '', 'value2' => ''],
        ['label' => '', 'value1' => '', 'value2' => ''],
        ['label' => '', 'value1' => '', 'value2' => ''],
    ]);
});

it('offers only blank size-chart rows for a section with none stored', function (): void {
    expect(DescriptionSectionRows::sizeChart(null))->toBe([
        ['label' => '', 'value1' => '', 'value2' => ''],
        ['label' => '', 'value1' => '', 'value2' => ''],
        ['label' => '', 'value1' => '', 'value2' => ''],
    ]);
});

it('pads existing spec rows with blanks', function (): void {
    expect(DescriptionSectionRows::specs([
        ['label' => 'Material', 'value' => 'Phoenix feather'],
    ]))->toBe([
        ['label' => 'Material', 'value' => 'Phoenix feather'],
        ['label' => '', 'value' => ''],
        ['label' => '', 'value' => ''],
        ['label' => '', 'value' => ''],
    ]);
});

it('offers only blank spec rows for a section with none stored', function (): void {
    expect(DescriptionSectionRows::specs(null))->toBe([
        ['label' => '', 'value' => ''],
        ['label' => '', 'value' => ''],
        ['label' => '', 'value' => ''],
    ]);
});

it('pads existing FAQ rows with blanks', function (): void {
    expect(DescriptionSectionRows::faq([
        ['question' => 'Does it work on non-magical wood?', 'answer' => 'No.'],
    ]))->toBe([
        ['question' => 'Does it work on non-magical wood?', 'answer' => 'No.'],
        ['question' => '', 'answer' => ''],
        ['question' => '', 'answer' => ''],
        ['question' => '', 'answer' => ''],
    ]);
});

it('offers only blank FAQ rows for a section with none stored', function (): void {
    expect(DescriptionSectionRows::faq(null))->toBe([
        ['question' => '', 'answer' => ''],
        ['question' => '', 'answer' => ''],
        ['question' => '', 'answer' => ''],
    ]);
});

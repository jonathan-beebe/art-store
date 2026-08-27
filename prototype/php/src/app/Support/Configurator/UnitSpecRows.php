<?php

declare(strict_types=1);

namespace App\Support\Configurator;

/**
 * The labeled measurement rows a piece's edit form prefills — one row per
 * existing `specs_json` entry, padded with blank rows so there is always
 * room to add more without JavaScript.
 */
final class UnitSpecRows
{
    /**
     * The blank rows appended after any existing measurements, for adding
     * more without JavaScript to grow the form.
     */
    private const int BLANK_ROWS = 3;

    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  array<string, int|float|string|bool>|null  $specs
     * @return list<array{label: string, value: string}>
     */
    public static function forEditing(?array $specs): array
    {
        $rows = [];

        foreach ($specs ?? [] as $label => $value) {
            $rows[] = ['label' => $label, 'value' => self::stringify($value)];
        }

        for ($i = 0; $i < self::BLANK_ROWS; $i++) {
            $rows[] = ['label' => '', 'value' => ''];
        }

        return $rows;
    }

    private static function stringify(int|float|string|bool $value): string
    {
        return is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
    }
}

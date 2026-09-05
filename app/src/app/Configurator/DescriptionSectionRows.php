<?php

declare(strict_types=1);

namespace App\Configurator;

/**
 * The rows a description section's editing form prefills — its saved
 * `body_json` rows, padded with blank rows so there is always room to add
 * more without JavaScript. One method per row shape a JSON-bodied section
 * kind carries (Size chart, Specs, Q & A).
 */
final class DescriptionSectionRows
{
    /**
     * The blank rows appended after any existing rows, for adding more
     * without JavaScript to grow the form.
     */
    private const int BLANK_ROWS = 3;

    private function __construct() {} // @codeCoverageIgnore

    /**
     * @param  array<int|string, mixed>|null  $stored
     * @return list<mixed>
     */
    public static function sizeChart(?array $stored): array
    {
        return self::padded($stored, ['label' => '', 'value1' => '', 'value2' => '']);
    }

    /**
     * @param  array<int|string, mixed>|null  $stored
     * @return list<mixed>
     */
    public static function specs(?array $stored): array
    {
        return self::padded($stored, ['label' => '', 'value' => '']);
    }

    /**
     * @param  array<int|string, mixed>|null  $stored
     * @return list<mixed>
     */
    public static function faq(?array $stored): array
    {
        return self::padded($stored, ['question' => '', 'answer' => '']);
    }

    /**
     * @param  array<int|string, mixed>|null  $stored
     * @param  array<string, mixed>  $blankRow
     * @return list<mixed>
     */
    private static function padded(?array $stored, array $blankRow): array
    {
        return array_values(array_merge($stored ?? [], array_fill(0, self::BLANK_ROWS, $blankRow)));
    }
}

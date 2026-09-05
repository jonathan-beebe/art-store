<?php

declare(strict_types=1);

namespace App\Configurator;

use App\Domain\Configurator\ModifierKind;

/**
 * The seller-facing phrase for a question's kind — a craft description of
 * what the buyer does, never the schema word ("text", "select") a seller has
 * no reason to learn.
 */
final class ModifierKindWord
{
    private function __construct() {} // @codeCoverageIgnore

    public static function forKind(ModifierKind $kind): string
    {
        return match ($kind) {
            ModifierKind::Text => 'they type it',
            ModifierKind::Select => 'they pick from your list',
            ModifierKind::Measurement => 'they give a measurement',
        };
    }
}

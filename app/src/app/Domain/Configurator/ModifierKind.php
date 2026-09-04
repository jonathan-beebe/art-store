<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

/**
 * What kind of answer a modifier (an order-line question) collects. `Select`
 * prices per chosen option; `Text` and `Measurement` price the answer itself
 * — `Measurement` on a rate times the buyer's value, the other two flat.
 */
enum ModifierKind: string
{
    case Text = 'text';
    case Select = 'select';
    case Measurement = 'measurement';

    public function pricesPerOption(): bool
    {
        return $this === self::Select;
    }
}

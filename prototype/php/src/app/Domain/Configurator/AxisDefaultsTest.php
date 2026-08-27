<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

it('carries an axis id, its option value ids, and the default among them', function (): void {
    $defaults = AxisDefaults::of('axs_1', ['ovl_1', 'ovl_2'], 'ovl_1');

    expect($defaults->axisId)->toBe('axs_1')
        ->and($defaults->optionValueIds)->toBe(['ovl_1', 'ovl_2'])
        ->and($defaults->defaultOptionValueId)->toBe('ovl_1');
});

<?php

declare(strict_types=1);

namespace App\Domain\Configurator;

it('says only select prices per option', function (): void {
    expect(ModifierKind::Select->pricesPerOption())->toBeTrue()
        ->and(ModifierKind::Text->pricesPerOption())->toBeFalse()
        ->and(ModifierKind::Measurement->pricesPerOption())->toBeFalse();
});

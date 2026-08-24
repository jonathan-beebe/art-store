<?php

declare(strict_types=1);

namespace App\Domain\Orders;

use App\Domain\CarriesRefusalData;
use App\Domain\DomainRuleViolation;

it('is a domain rule violation that carries its own refusal data', function (): void {
    $violation = new OrderPlacementRefused([
        new BlockedLine('lst_00000000000000000000000001', 'Harbour at Dusk', UnavailableReason::SoldOut),
    ]);

    expect($violation)->toBeInstanceOf(DomainRuleViolation::class)
        ->and($violation)->toBeInstanceOf(CarriesRefusalData::class);
});

it('names the one line it refused', function (): void {
    $violation = new OrderPlacementRefused([
        new BlockedLine('lst_00000000000000000000000001', 'Harbour at Dusk', UnavailableReason::SoldOut),
    ]);

    expect($violation->getMessage())->toBe('“Harbour at Dusk” is no longer available to buy.');
});

it('names every line it refused', function (): void {
    $violation = new OrderPlacementRefused([
        new BlockedLine('lst_00000000000000000000000001', 'Harbour at Dusk', UnavailableReason::SoldOut),
        new BlockedLine('lst_00000000000000000000000002', 'Low Tide', UnavailableReason::OffSale),
    ]);

    expect($violation->getMessage())->toBe('Some items are no longer available to buy: Harbour at Dusk, Low Tide.');
});

it('carries the blocked lines as data for the refused log line', function (): void {
    $violation = new OrderPlacementRefused([
        new BlockedLine('lst_00000000000000000000000001', 'Harbour at Dusk', UnavailableReason::SoldOut),
    ]);

    expect($violation->refusalData())->toBe([
        'blocked' => [
            ['listing_id' => 'lst_00000000000000000000000001', 'title' => 'Harbour at Dusk', 'reason' => 'sold_out'],
        ],
    ]);
});

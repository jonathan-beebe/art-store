<?php

declare(strict_types=1);

namespace App\Domain\Escrow;

it('names the four stages money passes through', function (): void {
    expect(array_column(LedgerEntryType::cases(), 'value'))
        ->toBe(['held', 'released', 'paid_out', 'refunded']);
});

it('reads its stored value back as a sentence', function (LedgerEntryType $type, string $expected): void {
    expect($type->label())->toBe($expected);
})->with([
    'held' => [LedgerEntryType::Held, 'Held'],
    'paid out' => [LedgerEntryType::PaidOut, 'Paid out'],
    'refunded' => [LedgerEntryType::Refunded, 'Refunded'],
]);

it('says where the money for one parcel stands', function (LedgerEntryType $type, string $expected): void {
    expect($type->escrowState())->toBe($expected);
})->with([
    'held' => [LedgerEntryType::Held, 'Held until delivery'],
    'released' => [LedgerEntryType::Released, 'Released to your balance'],
    'paid out' => [LedgerEntryType::PaidOut, 'Paid out'],
    'refunded' => [LedgerEntryType::Refunded, 'Returned to the buyer'],
]);

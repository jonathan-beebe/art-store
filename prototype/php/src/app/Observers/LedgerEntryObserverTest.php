<?php

declare(strict_types=1);

namespace App\Observers;

use App\Domain\Escrow\LedgerEntryType;
use App\Logging\StoryEvent;
use App\Models\LedgerEntry;
use App\Support\Story;
use Tests\CapturedStory;

it('logs every ledger entry as it is written', function (): void {
    $log = CapturedStory::capture();

    $entry = LedgerEntry::factory()->create(['type' => LedgerEntryType::Held, 'amount_cents' => 9000]);

    $line = $log->line('ledger.write', 'did');

    expect($line['level'])->toBe('debug')
        ->and($line['data'])->toBe([
            'ledger_entry_id' => $entry->id,
            'seller_id' => $entry->seller_id,
            'fulfillment_id' => $entry->fulfillment_id,
            'type' => 'held',
            'amount_cents' => 9000,
        ]);
});

it('names the payout it settled when one is set', function (): void {
    $log = CapturedStory::capture();

    $entry = LedgerEntry::factory()->paidOut()->create();

    $data = $log->line('ledger.write', 'did')['data'];
    assert(is_array($data));

    expect($data['payout_id'])->toBe($entry->payout_id);
});

it('joins the entry to the unit of work that wrote it', function (): void {
    $log = CapturedStory::capture();

    $story = Story::for(StoryEvent::OrderPay)->will('taking payment for an order');
    LedgerEntry::factory()->create();
    $story->did('took the payment');

    expect($log->line('ledger.write', 'did')['txn_id'])
        ->toBe($log->line('order.pay', 'will')['txn_id']);
});

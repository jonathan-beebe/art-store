<?php

declare(strict_types=1);

namespace App\Logging;

use App\Domain\Auth\ActorType;
use App\Domain\CarriesRefusalData;
use App\Domain\DomainRuleViolation;
use RuntimeException;
use Tests\CapturedStory;

it('opens with a will line and ends with a did line', function (): void {
    $log = CapturedStory::capture();

    Story::for(StoryEvent::OrderPlace)
        ->will('placing an order from the cart', ['cart_id' => 'crt_01J00000000000000000000ABC'])
        ->did('placed the order', ['order_id' => 'ord_01J00000000000000000000ABC']);

    expect($log->outline())->toBe(['order.place will', 'order.place did']);

    $will = $log->line('order.place', 'will');
    $did = $log->line('order.place', 'did');

    expect($will['msg'])->toBe('placing an order from the cart')
        ->and($will['data'])->toBe(['cart_id' => 'crt_01J00000000000000000000ABC'])
        ->and($did['data'])->toBe(['order_id' => 'ord_01J00000000000000000000ABC'])
        ->and($did['duration_ms'])->toBeInt();
});

it('carries one unit of work through every line between the will and its ending', function (): void {
    $log = CapturedStory::capture();

    $story = Story::for(StoryEvent::OrderPlace)->will('placing an order from the cart');
    Story::for(StoryEvent::LedgerWrite)->did('wrote a ledger entry');
    $story->did('placed the order');

    $lines = $log->lines();
    $unit = $lines[0]['txn_id'];

    expect($unit)->toStartWith('txn_')
        ->and($lines[1]['txn_id'])->toBe($unit)
        ->and($lines[2]['txn_id'])->toBe($unit);
});

it('names the innermost unit of work while one is open inside another', function (): void {
    $log = CapturedStory::capture();

    $outer = Story::for(StoryEvent::OrderPay)->will('taking payment for an order');
    $inner = Story::for(StoryEvent::FulfillmentShip)->will('marking a fulfillment shipped');
    $inner->did('marked the fulfillment shipped');
    Story::for(StoryEvent::LedgerWrite)->did('wrote a ledger entry');
    $outer->did('took the payment');

    $lines = $log->lines();

    expect($lines[1]['txn_id'])->not->toBe($lines[0]['txn_id'])
        ->and($lines[2]['txn_id'])->toBe($lines[1]['txn_id'])
        ->and($lines[3]['txn_id'])->toBe($lines[0]['txn_id'])
        ->and($lines[4]['txn_id'])->toBe($lines[0]['txn_id']);
});

it('leaves the unit of work off a line written with nothing open', function (): void {
    $log = CapturedStory::capture();

    Story::for(StoryEvent::LedgerWrite)->did('wrote a ledger entry');

    $line = $log->line('ledger.write', 'did');

    expect($line)->not->toHaveKey('txn_id')
        ->and($line)->not->toHaveKey('duration_ms')
        ->and($line['level'])->toBe('debug');
});

it('drops a unit of work left open by work that never ended', function (): void {
    $log = CapturedStory::capture();

    Story::for(StoryEvent::OrderPlace)->will('placing an order from the cart');

    Story::forget();

    Story::for(StoryEvent::LedgerWrite)->did('wrote a ledger entry');

    expect($log->line('ledger.write', 'did'))->not->toHaveKey('txn_id');
});

it('marks a long step inside the unit of work', function (): void {
    $log = CapturedStory::capture();

    Story::for(StoryEvent::PayoutRun)
        ->will('settling the weekly payout period')
        ->doing('paying one seller of many')
        ->did('settled the weekly payout period');

    expect($log->outline())->toBe(['payout.run will', 'payout.run doing', 'payout.run did']);
});

it('reads a refusal as info, because a rule held rather than something breaking', function (): void {
    $log = CapturedStory::capture();

    Story::for(StoryEvent::OrderPlace)
        ->will('placing an order from the cart')
        ->refused('That listing is no longer for sale.', ['listing_id' => 'lst_01J00000000000000000000ABC']);

    $line = $log->line('order.place', 'refused');

    expect($line['level'])->toBe('info')
        ->and($line['msg'])->toBe('That listing is no longer for sale.')
        ->and($line['duration_ms'])->toBeInt();
});

it('leaves duration_ms off a refusal with no will before it', function (): void {
    $log = CapturedStory::capture();

    Story::for(StoryEvent::ListingView)->refused('a view of this listing was already counted this hour');

    expect($log->line('listing.view', 'refused'))->not->toHaveKey('duration_ms');
});

it('drops the listing-view collapse to debug so it cannot drown the stream', function (): void {
    $log = CapturedStory::capture();

    Story::for(StoryEvent::ListingView)->refused('a view of this listing was already counted this hour');

    expect($log->line('listing.view', 'refused')['level'])->toBe('debug');
});

it('reads a failure as an error carrying the exception', function (): void {
    $log = CapturedStory::capture();

    $error = new RuntimeException('the checkout broke');

    Story::for(StoryEvent::HttpRequest)
        ->will('POST /checkout')
        ->failed($error, 'POST /checkout broke');

    $line = $log->line('http.request', 'failed');

    expect($line['level'])->toBe('error')
        ->and($line['error'])->toBe(['type' => RuntimeException::class, 'message' => 'the checkout broke'])
        ->and($line['duration_ms'])->toBeInt();
});

it('closes the unit of work whichever way the story ends', function (string $ending): void {
    $log = CapturedStory::capture();

    $story = Story::for(StoryEvent::OrderPlace)->will('placing an order from the cart');

    match ($ending) {
        'did' => $story->did('placed the order'),
        'refused' => $story->refused('An order needs at least one item.'),
        'failed' => $story->failed(new RuntimeException('broke'), 'the order broke'),
        default => throw new RuntimeException("No ending named {$ending}."),
    };

    Story::for(StoryEvent::LedgerWrite)->did('wrote a ledger entry');

    expect($log->line('ledger.write', 'did'))->not->toHaveKey('txn_id');
})->with(['did', 'refused', 'failed']);

it('tells the whole story of one unit of work, ending it with what the work did', function (): void {
    $log = CapturedStory::capture();

    $order = Story::for(StoryEvent::OrderPlace)->tell('placing an order from the cart', [
        'cart_id' => 'crt_01J00000000000000000000ABC',
    ], function (Story $story): string {
        $story->did('placed the order', ['order_id' => 'ord_01J00000000000000000000ABC']);

        return 'ord_01J00000000000000000000ABC';
    });

    expect($order)->toBe('ord_01J00000000000000000000ABC')
        ->and($log->outline())->toBe(['order.place will', 'order.place did']);
});

it('ends the unit of work as refused when the core turns the work down, and lets the refusal through', function (): void {
    $log = CapturedStory::capture();

    $refused = fn () => Story::for(StoryEvent::OrderPlace)->tell('placing an order from the cart', [
        'cart_id' => 'crt_01J00000000000000000000ABC',
    ], fn (): never => throw new DomainRuleViolation('That listing is no longer for sale.'));

    expect($refused)->toThrow(DomainRuleViolation::class, 'That listing is no longer for sale.');

    $line = $log->line('order.place', 'refused');

    expect($line['level'])->toBe('info')
        ->and($line['msg'])->toBe('That listing is no longer for sale.')
        ->and($line['data'])->toBe(['cart_id' => 'crt_01J00000000000000000000ABC']);
});

it('folds a violation carrying its own facts into the refused line, after what the unit of work already knew', function (): void {
    $log = CapturedStory::capture();

    $violation = new class('That listing is no longer for sale.') extends DomainRuleViolation implements CarriesRefusalData
    {
        public function refusalData(): array
        {
            return ['blocked' => ['lst_01J00000000000000000000ABC']];
        }
    };

    $refused = fn () => Story::for(StoryEvent::OrderPlace)->tell('placing an order from the cart', [
        'cart_id' => 'crt_01J00000000000000000000ABC',
    ], fn (): never => throw $violation);

    expect($refused)->toThrow(DomainRuleViolation::class);

    expect($log->line('order.place', 'refused')['data'])->toBe([
        'cart_id' => 'crt_01J00000000000000000000ABC',
        'blocked' => ['lst_01J00000000000000000000ABC'],
    ]);
});

it('leaves a fact the unit of work already named alone when a violation carries the same key', function (): void {
    $log = CapturedStory::capture();

    $violation = new class('That listing is no longer for sale.') extends DomainRuleViolation implements CarriesRefusalData
    {
        public function refusalData(): array
        {
            return ['cart_id' => 'crt_01J0000000000000000000OTHER', 'blocked' => ['lst_01J00000000000000000000ABC']];
        }
    };

    $refused = fn () => Story::for(StoryEvent::OrderPlace)->tell('placing an order from the cart', [
        'cart_id' => 'crt_01J00000000000000000000ABC',
    ], fn (): never => throw $violation);

    expect($refused)->toThrow(DomainRuleViolation::class);

    expect($log->line('order.place', 'refused')['data'])->toBe([
        'cart_id' => 'crt_01J00000000000000000000ABC',
        'blocked' => ['lst_01J00000000000000000000ABC'],
    ]);
});

it('ends the unit of work as failed when something nobody planned for escapes it', function (): void {
    $log = CapturedStory::capture();

    $broke = fn () => Story::for(StoryEvent::OrderPlace)->tell('placing an order from the cart', [
        'cart_id' => 'crt_01J00000000000000000000ABC',
    ], fn (): never => throw new RuntimeException('the orders table is gone'));

    expect($broke)->toThrow(RuntimeException::class, 'the orders table is gone');

    $line = $log->line('order.place', 'failed');

    expect($line['level'])->toBe('error')
        ->and($line['msg'])->toBe('❌ placing an order from the cart broke')
        ->and($line['error'])->toBe(['type' => RuntimeException::class, 'message' => 'the orders table is gone'])
        ->and($line['data'])->toBe(['cart_id' => 'crt_01J00000000000000000000ABC'])
        ->and($line['duration_ms'])->toBeInt();
});

it('leaves no unit of work open whichever way the work it was told left', function (callable $work): void {
    $log = CapturedStory::capture();

    try {
        Story::for(StoryEvent::OrderPlace)->tell('placing an order from the cart', [], $work);
    } catch (DomainRuleViolation|RuntimeException) {
        // The ending is the subject here, not what the caller does with the
        // exception that caused it.
    }

    Story::for(StoryEvent::LedgerWrite)->did('wrote a ledger entry');

    expect($log->line('ledger.write', 'did'))->not->toHaveKey('txn_id');
})->with([
    'did' => [fn (): callable => fn (Story $story) => $story->did('placed the order')],
    'refused' => [fn (): callable => fn (): never => throw new DomainRuleViolation('An order needs at least one item.')],
    'failed' => [fn (): callable => fn (): never => throw new RuntimeException('the orders table is gone')],
    'no ending of its own' => [fn (): callable => fn (): string => 'nothing was written'],
]);

it('puts the request marks on every line for the rest of the request', function (): void {
    $log = CapturedStory::capture();

    Story::follows('req_01J00000000000000000000ABC');
    Story::inSession('ses_01J00000000000000000000ABC');
    Story::actorIs(ActorType::Customer, 'cus_01J00000000000000000000ABC');

    Story::for(StoryEvent::CartAdd)->did('added the listing to the cart');

    expect($log->line('cart.add', 'did'))
        ->toHaveKey('request_id', 'req_01J00000000000000000000ABC')
        ->toHaveKey('session_id', 'ses_01J00000000000000000000ABC')
        ->toHaveKey('actor_type', 'customer')
        ->toHaveKey('actor_id', 'cus_01J00000000000000000000ABC');
});

it('binds the request marks for the body of asRequest and nothing outside it', function (): void {
    $log = CapturedStory::capture();

    Story::asRequest('req_01J00000000000000000000ABC', 'ses_01J00000000000000000000ABC', ActorType::Customer, 'cus_01J00000000000000000000ABC', function (): void {
        Story::for(StoryEvent::CartAdd)->did('added the listing to the cart');
    });

    Story::for(StoryEvent::LedgerWrite)->did('wrote a ledger entry');

    $bound = $log->line('cart.add', 'did');
    $unbound = $log->line('ledger.write', 'did');

    expect($bound)
        ->toHaveKey('request_id', 'req_01J00000000000000000000ABC')
        ->toHaveKey('session_id', 'ses_01J00000000000000000000ABC')
        ->toHaveKey('actor_type', 'customer')
        ->toHaveKey('actor_id', 'cus_01J00000000000000000000ABC');

    expect($unbound)->not->toHaveKey('request_id')
        ->and($unbound)->not->toHaveKey('session_id')
        ->and($unbound)->not->toHaveKey('actor_type')
        ->and($unbound)->not->toHaveKey('actor_id');
});

it('clears the request marks asRequest bound even when the body throws', function (): void {
    $log = CapturedStory::capture();

    $broke = fn () => Story::asRequest('req_01J00000000000000000000ABC', null, null, null, function (): never {
        throw new RuntimeException('the step broke');
    });

    expect($broke)->toThrow(RuntimeException::class);

    Story::for(StoryEvent::LedgerWrite)->did('wrote a ledger entry');

    expect($log->line('ledger.write', 'did'))->not->toHaveKey('request_id');
});

it('leaves a mark unbound rather than writing it as an empty string', function (): void {
    $log = CapturedStory::capture();

    Story::asRequest('req_01J00000000000000000000ABC', null, null, null, function (): void {
        Story::for(StoryEvent::CartAdd)->did('added the listing to the cart');
    });

    $line = $log->line('cart.add', 'did');

    expect($line)->toHaveKey('request_id', 'req_01J00000000000000000000ABC')
        ->and($line)->not->toHaveKey('session_id')
        ->and($line)->not->toHaveKey('actor_type')
        ->and($line)->not->toHaveKey('actor_id');
});

it('unbinds a request one asRequest call left off the marks the previous call bound', function (): void {
    $log = CapturedStory::capture();

    Story::asRequest('req_01J00000000000000000000ABC', 'ses_01J00000000000000000000ABC', ActorType::Customer, 'cus_01J00000000000000000000ABC', function (): void {
        //
    });

    Story::asRequest('req_01J00000000000000000000DEF', null, null, null, function (): void {
        Story::for(StoryEvent::CartAdd)->did('added the listing to the cart');
    });

    $line = $log->line('cart.add', 'did');

    expect($line)->toHaveKey('request_id', 'req_01J00000000000000000000DEF')
        ->and($line)->not->toHaveKey('session_id')
        ->and($line)->not->toHaveKey('actor_type')
        ->and($line)->not->toHaveKey('actor_id');
});

it('leaves a fact nobody has off the line rather than writing it as null', function (): void {
    $log = CapturedStory::capture();

    Story::for(StoryEvent::ConversationOpen)->did('opened the conversation', [
        'conversation_id' => 'cnv_01J00000000000000000000ABC',
        'listing_id' => null,
    ]);

    expect($log->line('conversation.open', 'did')['data'])
        ->toBe(['conversation_id' => 'cnv_01J00000000000000000000ABC']);
});

it('leaves data off a line that has no facts to carry', function (): void {
    $log = CapturedStory::capture();

    Story::for(StoryEvent::AppShutdown)->did('the application finished');

    expect($log->line('app.shutdown', 'did'))->not->toHaveKey('data');
});

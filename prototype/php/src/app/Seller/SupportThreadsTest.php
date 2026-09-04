<?php

declare(strict_types=1);

namespace App\Seller;

use App\Actions\Messaging\OpenThread;
use App\Domain\Messaging\MessageBody;
use App\Domain\Messaging\ThreadOpening;
use App\Domain\Messaging\ThreadTitle;

it('lists the seller\'s own support threads, newest first', function (): void {
    $seller = $this->seller();
    $older = app(OpenThread::class)(
        ThreadOpening::adminSeller($seller->id, ThreadTitle::of('First question')),
        $seller,
        MessageBody::of('One.'),
        $this->moment('2026-08-19 09:00:00'),
    );
    $newer = app(OpenThread::class)(
        ThreadOpening::adminSeller($seller->id, ThreadTitle::of('Second question')),
        $seller,
        MessageBody::of('Two.'),
        $this->moment('2026-08-19 10:00:00'),
    );

    $threads = SupportThreads::for($seller);

    expect($threads->threads)->toHaveCount(2)
        ->and($threads->threads[0]->id)->toBe($newer->id)
        ->and($threads->threads[1]->id)->toBe($older->id);
});

it('leaves out another seller\'s support threads and threads with buyers', function (): void {
    $seller = $this->seller();
    $other = $this->seller('Rye Press');
    app(OpenThread::class)(
        ThreadOpening::adminSeller($other->id, ThreadTitle::of('Not this seller')),
        $other,
        MessageBody::of('Hi.'),
        $this->moment('2026-08-19 09:00:00'),
    );

    expect(SupportThreads::for($seller)->threads)->toBeEmpty();
});

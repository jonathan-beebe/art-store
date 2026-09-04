<?php

declare(strict_types=1);

namespace App\Seller;

use App\Actions\Messaging\OpenThread;
use App\Actions\Messaging\PostMessage;
use App\Domain\Messaging\MessageBody;
use App\Domain\Messaging\ThreadOpening;
use App\Domain\Messaging\ThreadTitle;
use App\Domain\Seller\PresenceStatus;
use Database\Seeders\AdminSeeder;

it('lists every seeded admin under the desk role, in seed order', function (): void {
    $this->seed(AdminSeeder::class);
    $seller = $this->seller();

    $desk = SupportDesk::for($seller, $this->moment('2026-08-19 12:00:00'));

    expect($desk->people)->toHaveCount(2)
        ->and($desk->people[0]->name)->toBe('Jonathan Beebe')
        ->and($desk->people[1]->name)->toBe('Anna Schmunk')
        ->and($desk->people[0]->role)->toBe('Seller support')
        ->and($desk->people[1]->role)->toBe('Seller support');
});

it('reads every person as online during the configured hours', function (): void {
    $this->seed(AdminSeeder::class);
    $seller = $this->seller();

    $desk = SupportDesk::for($seller, $this->moment('2026-08-19 12:00:00'));

    expect($desk->people[0]->presence)->toBe(PresenceStatus::Online)
        ->and($desk->people[1]->presence)->toBe(PresenceStatus::Online);
});

it('has no last reply time when the seller has never written to the desk', function (): void {
    $this->seed(AdminSeeder::class);
    $seller = $this->seller();

    $desk = SupportDesk::for($seller, $this->moment('2026-08-19 12:00:00'));

    expect($desk->lastReplyTime)->toBeNull();
});

it('has no last reply time while the seller\'s last question is unanswered', function (): void {
    $this->seed(AdminSeeder::class);
    $seller = $this->seller();
    app(OpenThread::class)(
        ThreadOpening::adminSeller($seller->id, ThreadTitle::of('A question')),
        $seller,
        MessageBody::of('Anyone there?'),
        $this->moment('2026-08-19 09:00:00'),
    );

    $desk = SupportDesk::for($seller, $this->moment('2026-08-19 12:00:00'));

    expect($desk->lastReplyTime)->toBeNull();
});

it('reads the gap between the seller\'s last question and the desk\'s reply to it', function (): void {
    $this->seed(AdminSeeder::class);
    $seller = $this->seller();
    $admin = $this->admin();
    $conversation = app(OpenThread::class)(
        ThreadOpening::adminSeller($seller->id, ThreadTitle::of('A question')),
        $seller,
        MessageBody::of('Anyone there?'),
        $this->moment('2026-08-19 09:00:00'),
    );
    app(PostMessage::class)($conversation, $admin, MessageBody::of('Yes, here.'), $this->moment('2026-08-19 09:41:00'));

    $desk = SupportDesk::for($seller, $this->moment('2026-08-19 12:00:00'));

    expect($desk->lastReplyTime?->text)->toBe('41 minutes');
});

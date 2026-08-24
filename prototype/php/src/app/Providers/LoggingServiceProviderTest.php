<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Money\Money;
use App\Models\Seller;
use App\Notifications\ItemSold;
use App\Notifications\MagicLinkIssued;
use Illuminate\Database\Events\MigrationEnded;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\CapturedStory;

it('says the application booted', function (): void {
    $log = CapturedStory::capture();

    (new LoggingServiceProvider($this->app))->boot();

    expect($log->line('app.boot', 'did')['data'])->toBe(['env' => 'testing']);
});

it('says the application finished', function (): void {
    $log = CapturedStory::capture();

    $this->app->terminate();

    expect($log->linesFor('app.shutdown'))->toHaveCount(1);
});

it('tells the story of a migration run, with each migration under it', function (): void {
    $log = CapturedStory::capture();

    Event::dispatch(new MigrationsStarted('up'));
    Event::dispatch(new MigrationEnded(new class extends Migration {}, 'up', '2026_08_23_000000_create_things_table'));
    Event::dispatch(new MigrationsEnded('up'));

    expect($log->outline())->toBe(['migrate.run will', 'migrate.apply did', 'migrate.run did'])
        ->and($log->line('migrate.apply', 'did')['data'])->toBe([
            'migration' => '2026_08_23_000000_create_things_table',
            'method' => 'up',
        ])
        ->and($log->line('migrate.apply', 'did')['txn_id'])->toBe($log->line('migrate.run', 'will')['txn_id']);
});

it('reads a notification on the database channel as one written to the inbox', function (): void {
    $log = CapturedStory::capture();

    $seller = Seller::factory()->create();
    $seller->notify(new ItemSold('ord_01J00000000000000000000ABC', Money::fromCents(9000)));

    expect($log->line('notification.write', 'did')['data'])
        ->toHaveKey('channel', 'database')
        ->toHaveKey('notifiable_id', $seller->id);
});

it('reads a notification on any other channel as one delivered', function (): void {
    $log = CapturedStory::capture();

    Notification::route(MagicLinkIssued::channel(), 'ada@example.test')
        ->notify(new MagicLinkIssued('http://localhost:8000/auth/magic/abc'));

    $line = $log->line('notification.deliver', 'did');

    expect($line['data'])->toHaveKey('channel', MagicLinkIssued::channel())
        ->and($line['data'])->not->toHaveKey('notifiable_id');
});

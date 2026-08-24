<?php

declare(strict_types=1);

namespace App\Providers;

use App\Logging\StoryEvent;
use App\Support\Story;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Events\MigrationEnded;
use Illuminate\Database\Events\MigrationsEnded;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * The lines nothing in the application calls for: the process starting and
 * stopping, the schema moving, and a notification reaching someone. Each is
 * a framework event, so listening is what keeps the story whole without a
 * `Story` call scattered through code that has no other reason to know
 * about logging.
 */
class LoggingServiceProvider extends ServiceProvider
{
    /**
     * The channel that fills the in-app inbox, named the way `via()` names it.
     */
    private const string INBOX_CHANNEL = 'database';

    /**
     * The migration run currently open, so the run's ending can carry the
     * duration and the unit of work its individual migrations wrote under.
     */
    private ?Story $migrationRun = null;

    public function boot(): void
    {
        Story::for(StoryEvent::AppBoot)->did('the application booted', [
            'env' => $this->app->environment(),
        ]);

        // PHP's server runs one request per process, so a request's story
        // opens and closes between these two lines.
        $this->app->terminating(fn () => Story::for(StoryEvent::AppShutdown)->did('the application finished'));

        $this->announceMigrations();
        $this->announceNotifications();
    }

    private function announceMigrations(): void
    {
        Event::listen(MigrationsStarted::class, function (MigrationsStarted $event): void {
            $this->migrationRun = Story::for(StoryEvent::MigrateRun)
                ->will('running the migrations', ['method' => $event->method]);
        });

        Event::listen(MigrationEnded::class, fn (MigrationEnded $event) => Story::for(StoryEvent::MigrateApply)
            ->did('applied a migration', [
                'migration' => $event->name,
                'method' => $event->method,
            ]));

        Event::listen(MigrationsEnded::class, function (MigrationsEnded $event): void {
            $this->migrationRun?->did('ran the migrations', ['method' => $event->method]);
            $this->migrationRun = null;
        });
    }

    /**
     * The database channel is the in-app inbox, so writing to it is the
     * notification being written; every other channel carries it somewhere
     * else, which is the notification being delivered.
     */
    private function announceNotifications(): void
    {
        Event::listen(NotificationSent::class, function (NotificationSent $event): void {
            $data = [
                'notification_id' => $event->notification->id,
                'notifiable_id' => $this->notifiableId($event),
                'channel' => $event->channel,
            ];

            $event->channel === self::INBOX_CHANNEL
                ? Story::for(StoryEvent::NotificationWrite)->did('wrote a notification to the inbox', $data)
                : Story::for(StoryEvent::NotificationDeliver)->did('delivered a notification', $data);
        });
    }

    /**
     * A notification addressed to an address rather than a row — a sign-in
     * link — has no id to name, and the address itself never reaches a line.
     */
    private function notifiableId(NotificationSent $event): ?string
    {
        $notifiable = $event->notifiable;

        if (! $notifiable instanceof Model) {
            return null;
        }

        $key = $notifiable->getKey();

        return is_scalar($key) ? (string) $key : null;
    }
}

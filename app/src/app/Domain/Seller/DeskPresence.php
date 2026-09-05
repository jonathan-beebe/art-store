<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use DateTimeImmutable;

/**
 * The support desk's presence, read from configured weekday hours rather
 * than anything live — a static approximation for this cut (FEAT-061's
 * discovery notes). Hours are "HH:MM" in the app's own timezone; the desk
 * keeps Monday to Friday.
 */
final readonly class DeskPresence
{
    private function __construct(
        public PresenceStatus $status,
        public string $text,
    ) {}

    public static function of(DateTimeImmutable $now, string $opensAt, string $closesAt): self
    {
        return self::isOpenAt($now, $opensAt, $closesAt)
            ? new self(PresenceStatus::Online, 'Online now')
            : new self(PresenceStatus::Away, 'Back '.self::nextOpenLabel($now, $opensAt, $closesAt).' at '.$opensAt);
    }

    private static function isOpenAt(DateTimeImmutable $now, string $opensAt, string $closesAt): bool
    {
        $weekday = (int) $now->format('N');
        $time = $now->format('H:i');

        return $weekday <= 5 && $time >= $opensAt && $time < $closesAt;
    }

    /**
     * "today" before the desk opens on a weekday it keeps; "tomorrow" once
     * it has closed on any weekday before Friday; "Monday" from Friday's
     * close through the weekend.
     */
    private static function nextOpenLabel(DateTimeImmutable $now, string $opensAt, string $closesAt): string
    {
        $weekday = (int) $now->format('N');
        $time = $now->format('H:i');

        if ($weekday <= 5 && $time < $opensAt) {
            return 'today';
        }

        if ($weekday <= 5 && $time >= $closesAt && $weekday < 5) {
            return 'tomorrow';
        }

        return 'Monday';
    }
}

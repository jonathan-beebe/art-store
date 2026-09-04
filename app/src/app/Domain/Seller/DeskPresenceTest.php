<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use DateTimeImmutable;

it('reads online during weekday hours', function (): void {
    $presence = DeskPresence::of(new DateTimeImmutable('2026-08-19 12:00:00'), '09:00', '17:00'); // Wednesday

    expect($presence->status)->toBe(PresenceStatus::Online)
        ->and($presence->text)->toBe('Online now');
});

it('reads away before the desk opens, back later today', function (): void {
    $presence = DeskPresence::of(new DateTimeImmutable('2026-08-19 08:00:00'), '09:00', '17:00'); // Wednesday

    expect($presence->status)->toBe(PresenceStatus::Away)
        ->and($presence->text)->toBe('Back today at 09:00');
});

it('reads away after close on a weekday before friday, back tomorrow', function (): void {
    $presence = DeskPresence::of(new DateTimeImmutable('2026-08-19 18:00:00'), '09:00', '17:00'); // Wednesday

    expect($presence->text)->toBe('Back tomorrow at 09:00');
});

it('reads away after close on friday, back monday', function (): void {
    $presence = DeskPresence::of(new DateTimeImmutable('2026-08-21 18:00:00'), '09:00', '17:00'); // Friday

    expect($presence->text)->toBe('Back Monday at 09:00');
});

it('reads away all weekend, back monday', function (string $moment): void {
    $presence = DeskPresence::of(new DateTimeImmutable($moment), '09:00', '17:00');

    expect($presence->status)->toBe(PresenceStatus::Away)
        ->and($presence->text)->toBe('Back Monday at 09:00');
})->with([
    'saturday morning' => ['2026-08-22 08:00:00'],
    'saturday evening' => ['2026-08-22 20:00:00'],
    'sunday' => ['2026-08-23 12:00:00'],
]);

it('reads away before opening on a friday, back later today', function (): void {
    $presence = DeskPresence::of(new DateTimeImmutable('2026-08-21 08:00:00'), '09:00', '17:00'); // Friday

    expect($presence->text)->toBe('Back today at 09:00');
});

<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use DateTimeImmutable;

it('reads a gap under a minute as "under a minute"', function (): void {
    $reply = ReplyTime::between(new DateTimeImmutable('2026-08-19 09:00:00'), new DateTimeImmutable('2026-08-19 09:00:40'));

    expect($reply->text)->toBe('under a minute');
});

it('reads a gap in minutes, singular and plural', function (string $answeredAt, string $expected): void {
    $reply = ReplyTime::between(new DateTimeImmutable('2026-08-19 09:00:00'), new DateTimeImmutable($answeredAt));

    expect($reply->text)->toBe($expected);
})->with([
    'one minute' => ['2026-08-19 09:01:00', '1 minute'],
    'forty-one minutes' => ['2026-08-19 09:41:00', '41 minutes'],
    'fifty-nine minutes' => ['2026-08-19 09:59:00', '59 minutes'],
]);

it('reads a gap of an hour or more in hours, singular and plural', function (string $answeredAt, string $expected): void {
    $reply = ReplyTime::between(new DateTimeImmutable('2026-08-19 09:00:00'), new DateTimeImmutable($answeredAt));

    expect($reply->text)->toBe($expected);
})->with([
    'exactly one hour' => ['2026-08-19 10:00:00', '1 hour'],
    'two hours' => ['2026-08-19 11:00:00', '2 hours'],
    'a day' => ['2026-08-20 09:00:00', '24 hours'],
]);

<?php

declare(strict_types=1);

namespace App\Domain\Seller;

use App\Domain\Analytics\AnalyticsEventName;
use DateTimeImmutable;
use DateTimeZone;

it('collides on the same event name, article, seller, and UTC day', function (): void {
    $first = HelpArticleFeedbackCollapse::dedupeKey(AnalyticsEventName::HelpAnswered, 'printing-a-label-from-an-order', 'sel_1', new DateTimeImmutable('2026-09-03 09:01:00', new DateTimeZone('UTC')));
    $second = HelpArticleFeedbackCollapse::dedupeKey(AnalyticsEventName::HelpAnswered, 'printing-a-label-from-an-order', 'sel_1', new DateTimeImmutable('2026-09-03 22:59:00', new DateTimeZone('UTC')));

    expect($first)->toBe($second)
        ->and($first)->toBe('help:help.answered:article:printing-a-label-from-an-order:seller:sel_1:day:2026-09-03');
});

it('reads the day in UTC whatever zone the moment carries', function (): void {
    $key = HelpArticleFeedbackCollapse::dedupeKey(AnalyticsEventName::HelpAnswered, 'printing-a-label-from-an-order', 'sel_1', new DateTimeImmutable('2026-09-03 01:15:00', new DateTimeZone('+03:00')));

    expect($key)->toBe('help:help.answered:article:printing-a-label-from-an-order:seller:sel_1:day:2026-09-02');
});

it('keeps a different event name, article, seller, or day apart', function (AnalyticsEventName $name, string $slug, string $sellerId, string $at): void {
    $key = HelpArticleFeedbackCollapse::dedupeKey($name, $slug, $sellerId, new DateTimeImmutable($at, new DateTimeZone('UTC')));

    expect($key)->not->toBe('help:help.answered:article:printing-a-label-from-an-order:seller:sel_1:day:2026-09-03');
})->with([
    'no, not answered' => [AnalyticsEventName::HelpUnanswered, 'printing-a-label-from-an-order', 'sel_1', '2026-09-03 09:01:00'],
    'another article' => [AnalyticsEventName::HelpAnswered, 'when-money-reaches-your-account', 'sel_1', '2026-09-03 09:01:00'],
    'another seller' => [AnalyticsEventName::HelpAnswered, 'printing-a-label-from-an-order', 'sel_2', '2026-09-03 09:01:00'],
    'the next day' => [AnalyticsEventName::HelpAnswered, 'printing-a-label-from-an-order', 'sel_1', '2026-09-04 09:01:00'],
]);

<?php

declare(strict_types=1);

use App\Domain\Analytics\BarStripBar;

it('renders one rect per bar, each at an increasing x, carrying its tooltip', function (): void {
    $bars = [
        new BarStripBar(2, 'Aug 1: 0'),
        new BarStripBar(13, 'Aug 2: 5'),
        new BarStripBar(26, 'Aug 3: 10'),
    ];

    $html = (string) $this->blade('<x-admin.analytics.bar-strip :bars="$bars" :height="26" class="text-stone-400" />', ['bars' => $bars]);

    expect(substr_count($html, '<rect'))->toBe(3)
        ->and($html)->toContain('<title>Aug 1: 0</title>')
        ->and($html)->toContain('<title>Aug 2: 5</title>')
        ->and($html)->toContain('<title>Aug 3: 10</title>');

    preg_match_all('/<rect\s+x="(\d+)"/', $html, $matches);

    expect(array_map('intval', $matches[1]))->toBe([0, 3, 6]);
});

it('overrides a flagged bar\'s own color, leaving the rest to the svg class', function (): void {
    $bars = [
        new BarStripBar(10, 'quiet', false),
        new BarStripBar(26, 'flagged', true),
    ];

    $html = (string) $this->blade('<x-admin.analytics.bar-strip :bars="$bars" :height="26" class="text-stone-500" />', ['bars' => $bars]);

    expect(substr_count($html, 'text-red-600 dark:text-red-500'))->toBe(1);
});

it('closes the gap between bars once the series passes thirty-one days', function (): void {
    $bars = array_fill(0, 90, new BarStripBar(10, 'day'));

    $html = (string) $this->blade('<x-admin.analytics.bar-strip :bars="$bars" :height="26" />', ['bars' => $bars]);

    expect(substr_count($html, '<rect'))->toBe(90)
        ->and($html)->toContain('width="3"')
        ->and($html)->not->toContain('width="2"');
});

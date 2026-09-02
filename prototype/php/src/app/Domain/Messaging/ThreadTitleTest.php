<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use App\Domain\DomainRuleViolation;

it('holds a title typed by a seller or a customer', function (): void {
    expect(ThreadTitle::of('Payout timing')->value)->toBe('Payout timing');
});

it('refuses a title longer than the limit', function (): void {
    ThreadTitle::of(str_repeat('a', 121));
})->throws(DomainRuleViolation::class, 'A thread title cannot be longer than 120 characters.');

it('accepts a title at the limit', function (): void {
    $title = str_repeat('a', 120);

    expect(ThreadTitle::of($title)->value)->toBe($title);
});

it('derives a title from a question\'s first line', function (): void {
    expect(ThreadTitle::fromBody("Does this ship framed?\nAlso, is it signed?")->value)->toBe('Does this ship framed?');
});

it('cuts a long first line at 80 characters with an ellipsis', function (): void {
    $line = str_repeat('a', 90);

    expect(ThreadTitle::fromBody($line)->value)->toBe(str_repeat('a', 79).'…');
});

it('leaves a first line at or under 80 characters untouched', function (): void {
    $line = str_repeat('a', 80);

    expect(ThreadTitle::fromBody($line)->value)->toBe($line);
});

it('trims the body before reading its first line', function (): void {
    expect(ThreadTitle::fromBody("  Does this ship framed?  \nMore.")->value)->toBe('Does this ship framed?');
});

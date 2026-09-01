<?php

declare(strict_types=1);

namespace App\Logging\Admin;

$row = function (?string $level): LogRow {
    return LogRow::fromDatabase(['id' => 1, 'ts' => 't', 'level' => $level] + array_fill_keys(
        ['event', 'phase', 'msg', 'request_id', 'session_id', 'actor_type', 'actor_id', 'txn_id', 'duration_ms', 'data', 'error'],
        null,
    ));
};

it('reads the error level', function (): void {
    expect(LogSeverity::ofLevel('error'))->toBe(LogSeverity::Error);
});

it('reads the warn level', function (): void {
    expect(LogSeverity::ofLevel('warn'))->toBe(LogSeverity::Warn);
});

it('reads any other level as none', function (): void {
    expect(LogSeverity::ofLevel('info'))->toBe(LogSeverity::None);
});

it('reads a missing level as none', function (): void {
    expect(LogSeverity::ofLevel(null))->toBe(LogSeverity::None);
});

it('finds no severity in an empty list of lines', function (): void {
    expect(LogSeverity::worstOf([]))->toBe(LogSeverity::None);
});

it('finds no severity when every line is info', function () use ($row): void {
    expect(LogSeverity::worstOf([$row('info'), $row(null)]))->toBe(LogSeverity::None);
});

it('finds warn when the worst line is a warn', function () use ($row): void {
    expect(LogSeverity::worstOf([$row('info'), $row('warn')]))->toBe(LogSeverity::Warn);
});

it('lets an error win over a warn', function () use ($row): void {
    expect(LogSeverity::worstOf([$row('warn'), $row('error')]))->toBe(LogSeverity::Error);
});

it('finds the error regardless of where it falls in the list', function () use ($row): void {
    expect(LogSeverity::worstOf([$row('error'), $row('warn'), $row('info')]))->toBe(LogSeverity::Error);
});

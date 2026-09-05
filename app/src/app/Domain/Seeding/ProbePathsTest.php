<?php

declare(strict_types=1);

namespace App\Domain\Seeding;

it('names more than a dozen credential and admin paths', function (): void {
    expect(count(ProbePaths::paths()))->toBeGreaterThan(12);
});

it('names no path twice', function (): void {
    $paths = ProbePaths::paths();

    expect($paths)->toBe(array_unique($paths));
});

it('answers every path not found, except the one that redirects to a real login', function (): void {
    foreach (ProbePaths::paths() as $path) {
        $status = ProbePaths::statusFor($path);

        expect($status)->toBe($path === '/admin' ? 302 : 404);
    }
});

it('answers not found for a path it never named', function (): void {
    expect(ProbePaths::statusFor('/never-listed'))->toBe(404);
});

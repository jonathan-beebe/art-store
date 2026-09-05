<?php

declare(strict_types=1);

namespace App\Models;

it('resolves a display name', function (array $attributes, string $expected): void {
    /** @var array<string, mixed> $attributes */
    expect((new Admin($attributes))->displayName())->toBe($expected);
})->with([
    'name wins' => [['email' => 'ops@example.com', 'name' => 'Nia Ops'], 'Nia Ops'],
    'email alone' => [['email' => 'ops@example.com'], 'ops@example.com'],
]);

it('is named by the morph alias its notifications and messages are addressed to', function (): void {
    expect((new Admin)->getMorphClass())->toBe('admin');
});

it('names the first admin by id as the platform admin', function (): void {
    $first = $this->admin();
    $this->admin();

    expect(Admin::platformAdmin()?->is($first))->toBeTrue();
});

it('has no platform admin when none is seeded', function (): void {
    expect(Admin::platformAdmin())->toBeNull();
});

it('admits an address with an admin row', function (): void {
    Admin::factory()->create(['email' => 'ops@example.com']);

    expect(Admin::admitsEmail('Ops@Example.com'))->toBeTrue();
});

it('does not admit an address with no admin row', function (): void {
    expect(Admin::admitsEmail('nobody@example.com'))->toBeFalse();
});

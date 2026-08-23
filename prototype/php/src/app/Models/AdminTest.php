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

it('reads the conversations it is a participant in', function (): void {
    $admin = $this->admin();
    Conversation::factory()->adminSeller()->create(['admin_id' => $admin->id]);
    Conversation::factory()->adminSeller()->create();

    expect($admin->conversations()->count())->toBe(1);
});

it('reads the messages it sent', function (): void {
    $admin = $this->admin();
    $conversation = Conversation::factory()->adminSeller()->create(['admin_id' => $admin->id]);
    Message::factory()->from($admin)->create(['conversation_id' => $conversation->id]);
    Message::factory()->create(['conversation_id' => $conversation->id]);

    expect($admin->sentMessages()->count())->toBe(1);
});

it('names the first admin by id as the platform admin', function (): void {
    $first = $this->admin();
    $this->admin();

    expect(Admin::platformAdmin()?->is($first))->toBeTrue();
});

it('has no platform admin when none is seeded', function (): void {
    expect(Admin::platformAdmin())->toBeNull();
});

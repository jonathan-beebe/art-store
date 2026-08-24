<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Models\Admin;
use App\Models\Conversation;

it('opens the visitors admin thread and lands on it', function (): void {
    $admin = $this->admin();
    $visitor = $this->visitor();

    $response = $this->get('/support');

    $conversation = Conversation::sole();
    expect($conversation->admin_id)->toBe($admin->id)
        ->and($conversation->customer_id)->toBe($visitor->id);
    $response->assertRedirect(route('shop.messages.show', $conversation));
});

it('lands on the same thread a second time', function (): void {
    $this->admin();
    $this->visitor();
    $this->get('/support');

    $this->get('/support');

    expect(Conversation::count())->toBe(1);
});

it('opens against the first admin by id', function (): void {
    $first = $this->admin();
    Admin::factory()->create();
    $this->visitor();

    $this->get('/support');

    expect(Conversation::sole()->admin_id)->toBe($first->id);
});

it('redirects with an error when no admin has been seeded', function (): void {
    $this->visitor();

    $response = $this->get('/support');

    $response->assertSessionHasErrors('support');
    expect(Conversation::count())->toBe(0);
});

it('works for a visitor who has never signed in', function (): void {
    $this->admin();

    $response = $this->get('/support');

    $response->assertRedirect();
    expect(Conversation::count())->toBe(1);
});

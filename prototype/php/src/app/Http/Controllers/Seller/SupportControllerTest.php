<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Models\Admin;
use App\Models\Conversation;

it('opens the sellers admin thread and lands on it', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->get('/seller/support');

    $conversation = Conversation::sole();
    expect($conversation->admin_id)->toBe($admin->id)
        ->and($conversation->seller_id)->toBe($seller->id);
    $response->assertRedirect(route('seller.messages.show', $conversation));
});

it('lands on the same thread a second time', function (): void {
    $this->admin();
    $seller = $this->seller();
    $this->actingAs($seller, 'seller')->get('/seller/support');

    $this->actingAs($seller, 'seller')->get('/seller/support');

    expect(Conversation::count())->toBe(1);
});

it('opens against the first admin by id', function (): void {
    $first = $this->admin();
    Admin::factory()->create();
    $seller = $this->seller();

    $this->actingAs($seller, 'seller')->get('/seller/support');

    expect(Conversation::sole()->admin_id)->toBe($first->id);
});

it('redirects with an error when no admin has been seeded', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/support');

    $response->assertSessionHasErrors('support');
    expect(Conversation::count())->toBe(0);
});

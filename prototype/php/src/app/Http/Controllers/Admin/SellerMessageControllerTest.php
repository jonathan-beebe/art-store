<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\RateLimiting\RateLimitValue;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Config;

it('opens the sellers admin thread, posts the message, and lands on it', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();

    $response = $this->actingAs($admin, 'admin')
        ->post("/admin/sellers/{$seller->id}/messages", ['body' => 'Please review your listing photos.']);

    $conversation = Conversation::sole();
    expect($conversation->kind->value)->toBe('admin_seller')
        ->and($conversation->seller_id)->toBe($seller->id)
        ->and($conversation->admin_id)->toBe($admin->id)
        ->and($conversation->subject_key)->toBeNull();
    expect(Message::where('conversation_id', $conversation->id)->where('body', 'Please review your listing photos.')->exists())->toBeTrue();
    $response->assertRedirect(route('admin.messages.show', $conversation));
});

it('opens a fresh thread every time, rather than finding one for the same seller', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();
    $this->actingAs($admin, 'admin')->post("/admin/sellers/{$seller->id}/messages", ['body' => 'First message.']);

    $this->actingAs($admin, 'admin')->post("/admin/sellers/{$seller->id}/messages", ['body' => 'Second message.']);

    expect(Conversation::count())->toBe(2);
});

it('refuses a message longer than the message limit', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($this->admin(), 'admin')
        ->post("/admin/sellers/{$seller->id}/messages", ['body' => str_repeat('a', 2001)]);

    $response->assertSessionHasErrors('body');
    expect(Conversation::count())->toBe(0);
});

it('answers not found for a seller id that matches nothing', function (): void {
    $response = $this->actingAs($this->admin(), 'admin')
        ->post('/admin/sellers/999999/messages', ['body' => 'Please review your listing photos.']);

    $response->assertNotFound();
    expect(Conversation::count())->toBe(0);
});

it('lets a second admin see the thread the first admin opened, the desk is collective', function (): void {
    $seller = $this->seller();
    $first = $this->admin();
    $second = $this->admin();
    $this->actingAs($first, 'admin')->post("/admin/sellers/{$seller->id}/messages", ['body' => 'From the desk.']);
    $conversation = Conversation::sole();

    $response = $this->actingAs($second, 'admin')->get("/admin/messages/{$conversation->id}");

    $response->assertOk();
    $response->assertSee('From the desk.');
});

it('trips the message-post limit, handing the seller page back with the message still in the box', function (): void {
    Config::set('rate_limits.message_post', RateLimitValue::parse('1/1h', 'RATE_LIMIT_MESSAGE_POST'));
    $admin = $this->admin();
    $firstSeller = $this->seller('Blue Kiln Studio');
    $secondSeller = $this->seller('Rye Press');
    $this->actingAs($admin, 'admin')->post("/admin/sellers/{$firstSeller->id}/messages", ['body' => 'First message.']);

    $response = $this->actingAs($admin, 'admin')
        ->post("/admin/sellers/{$secondSeller->id}/messages", ['body' => 'Second message.']);

    $response->assertStatus(429);
    $response->assertHeader('Retry-After');
    $response->assertSee('Too many requests', escape: false);
    $response->assertSee('Message seller');
    $response->assertSee('Rye Press');
    $response->assertSee('>Second message.</textarea>', escape: false);
    expect(Conversation::count())->toBe(1);
});

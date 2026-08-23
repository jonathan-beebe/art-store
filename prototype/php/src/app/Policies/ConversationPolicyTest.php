<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Conversation;
use App\Models\CustomerBlock;
use Illuminate\Auth\Access\Response;

it('lets each participant view their own thread', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
    ]);
    $policy = new ConversationPolicy;

    expect($policy->view($seller, $conversation)->allowed())->toBeTrue()
        ->and($policy->view($customer, $conversation)->allowed())->toBeTrue();
});

it('answers not found for an actor who is not a participant', function (): void {
    $conversation = Conversation::factory()->listingQuestion()->create();

    $response = (new ConversationPolicy)->view($this->seller('Other Studio'), $conversation);

    expect($response)->toBeInstanceOf(Response::class);
    /** @var Response $response */
    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);
});

it('answers not found for an admin on a thread with no admin column', function (): void {
    $conversation = Conversation::factory()->listingQuestion()->create();

    $response = (new ConversationPolicy)->view($this->admin(), $conversation);

    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);
});

it('lets a seller and an admin post without asking about standing', function (): void {
    $admin = $this->admin();
    $seller = $this->seller();
    $conversation = Conversation::factory()->adminSeller()->create([
        'admin_id' => $admin->id,
        'seller_id' => $seller->id,
    ]);
    $policy = new ConversationPolicy;

    expect($policy->post($admin, $conversation)->allowed())->toBeTrue()
        ->and($policy->post($seller, $conversation)->allowed())->toBeTrue();
});

it('lets an unblocked customer post', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
    ]);

    expect((new ConversationPolicy)->post($customer, $conversation)->allowed())->toBeTrue();
});

it('reads a thread but cannot post while blocked', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    CustomerBlock::factory()->create(['customer_id' => $customer->id]);
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
    ]);
    $policy = new ConversationPolicy;

    expect($policy->view($customer, $conversation)->allowed())->toBeTrue()
        ->and($policy->post($customer, $conversation)->allowed())->toBeFalse();
});

it('answers not found on post for a thread the actor is not in, before standing is asked', function (): void {
    $conversation = Conversation::factory()->listingQuestion()->create();

    $response = (new ConversationPolicy)->post($this->verifiedCustomer(), $conversation);

    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);
});

<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Admin;
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

it('lets every admin view every thread, including a desk thread nobody has answered yet', function (): void {
    $firstAdmin = Admin::factory()->create();
    $secondAdmin = Admin::factory()->create();
    $conversation = Conversation::factory()->adminSeller()->create();

    expect((new ConversationPolicy)->view($firstAdmin, $conversation)->allowed())->toBeTrue()
        ->and((new ConversationPolicy)->view($secondAdmin, $conversation)->allowed())->toBeTrue();
});

it('lets an admin view an oversight thread between a seller and a customer', function (): void {
    $admin = Admin::factory()->create();
    $conversation = Conversation::factory()->listingQuestion()->create();

    expect((new ConversationPolicy)->view($admin, $conversation)->allowed())->toBeTrue();
});

it('lets a seller and an admin post without asking about standing', function (): void {
    $admin = Admin::factory()->create();
    $seller = $this->seller();
    $conversation = Conversation::factory()->adminSeller()->create(['seller_id' => $seller->id]);
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

it('never lets the desk post into a seller/customer thread, the two-sides invariant', function (): void {
    $admin = Admin::factory()->create();
    $conversation = Conversation::factory()->listingQuestion()->create();

    $response = (new ConversationPolicy)->post($admin, $conversation);

    expect($response->allowed())->toBeFalse()
        ->and($response->status())->not->toBe(404);
});

it('lets the desk resolve and reopen a support thread', function (): void {
    $admin = Admin::factory()->create();
    $open = Conversation::factory()->adminSeller()->create();
    $resolved = Conversation::factory()->adminSeller()->create(['resolved_at' => now()]);
    $policy = new ConversationPolicy;

    expect($policy->resolve($admin, $open)->allowed())->toBeTrue()
        ->and($policy->resolve($admin, $resolved)->allowed())->toBeFalse()
        ->and($policy->reopen($admin, $resolved)->allowed())->toBeTrue()
        ->and($policy->reopen($admin, $open)->allowed())->toBeFalse();
});

it('lets the seller resolve and reopen a listing question, never the customer', function (): void {
    $seller = $this->seller();
    $customer = $this->verifiedCustomer();
    $conversation = Conversation::factory()->listingQuestion()->create([
        'seller_id' => $seller->id,
        'customer_id' => $customer->id,
    ]);
    $policy = new ConversationPolicy;

    expect($policy->resolve($seller, $conversation)->allowed())->toBeTrue()
        ->and($policy->resolve($customer, $conversation)->allowed())->toBeFalse();
});

it('answers not found resolving a thread the actor is not in', function (): void {
    $conversation = Conversation::factory()->listingQuestion()->create();

    $response = (new ConversationPolicy)->resolve($this->seller('Other Studio'), $conversation);

    expect($response->denied())->toBeTrue()
        ->and($response->status())->toBe(404);
});

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Domain\Fulfillment\DefaultFlow;
use App\Models\FulfillmentFlow;
use App\Models\FulfillmentFlowStep;

it('renders the sellers flow name and step labels', function (): void {
    $seller = $this->seller();
    $this->flowFor($seller, 'How Molly Ships');

    $response = $this->actingAs($seller, 'seller')->get('/seller/orders/flow');

    $response->assertOk();
    $response->assertSee('How Molly Ships');
    $response->assertSee('Label printed');
    $response->assertSee('Packed');
});

it('shows the default name and an empty list for a seller with no flow yet, creating no row', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/orders/flow');

    $response->assertOk();
    $response->assertSee(DefaultFlow::NAME);
    expect(FulfillmentFlow::count())->toBe(0);
});

it('saves a rename, an added step, a renamed existing step, a removed step, and a reorder in one submit', function (): void {
    $seller = $this->seller();
    [$labelStep, $packStep] = $this->flowFor($seller, 'How Molly Ships');
    $flow = $labelStep->fulfillmentFlow;

    $response = $this->actingAs($seller, 'seller')->put('/seller/orders/flow', [
        'name' => 'How the Burrow Ships',
        'steps' => [
            ['id' => $labelStep->id, 'label' => 'Label printed', 'action' => 'print_label', 'position' => 3],
            ['id' => $packStep->id, 'label' => 'Carefully packed', 'action' => 'none', 'position' => 2, 'remove' => '0'],
            ['id' => '', 'label' => 'Kiln cooled', 'action' => 'none', 'position' => 1],
        ],
    ]);

    $response->assertRedirect(route('seller.orders.flow.edit'));
    $response->assertSessionHas('status', 'Flow saved.');

    $flow->refresh()->load('steps');
    expect($flow->name)->toBe('How the Burrow Ships')
        ->and($flow->steps->pluck('label')->all())->toBe(['Kiln cooled', 'Carefully packed', 'Label printed'])
        ->and($flow->steps->firstWhere('label', 'Label printed')?->id)->toBe($labelStep->id)
        ->and($flow->steps->firstWhere('label', 'Carefully packed')?->id)->toBe($packStep->id);
});

it('removes a step ticked for removal', function (): void {
    $seller = $this->seller();
    [$labelStep, $packStep] = $this->flowFor($seller, 'How Molly Ships');

    $this->actingAs($seller, 'seller')->put('/seller/orders/flow', [
        'name' => 'How Molly Ships',
        'steps' => [
            ['id' => $labelStep->id, 'label' => 'Label printed', 'action' => 'print_label', 'position' => 1],
            ['id' => $packStep->id, 'label' => 'Packed', 'action' => 'none', 'position' => 2, 'remove' => '1'],
        ],
    ]);

    expect(FulfillmentFlowStep::find($packStep->id))->toBeNull()
        ->and(FulfillmentFlowStep::find($labelStep->id))->not->toBeNull();
});

it('adds nothing for a submitted row with a blank label', function (): void {
    $seller = $this->seller();

    $this->actingAs($seller, 'seller')->put('/seller/orders/flow', [
        'name' => 'How Molly Ships',
        'steps' => [
            ['id' => '', 'label' => 'Packed', 'action' => 'none', 'position' => 1],
            ['id' => '', 'label' => '', 'action' => 'none', 'position' => 2],
        ],
    ]);

    $flow = FulfillmentFlow::where('seller_id', $seller->id)->sole();
    expect($flow->steps()->count())->toBe(1);
});

it('never touches another sellers flow', function (): void {
    $other = $this->seller('Lovegood Curiosities');
    [$otherLabelStep] = $this->flowFor($other, 'How Molly Ships');
    $otherFlow = $otherLabelStep->fulfillmentFlow;

    $this->actingAs($this->seller(), 'seller')->put('/seller/orders/flow', [
        'name' => 'Mine',
        'steps' => [['id' => '', 'label' => 'My step', 'action' => 'none', 'position' => 1]],
    ]);

    expect($otherFlow->refresh()->name)->toBe('How Molly Ships')
        ->and(FulfillmentFlowStep::find($otherLabelStep->id))->not->toBeNull();
});

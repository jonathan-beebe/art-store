<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seller;

use App\Models\FulfillmentFlow;
use App\Models\FulfillmentFlowStep;

it('lists a sellers workflows, the default marked, with step counts and the listings that name each', function (): void {
    $seller = $this->seller();
    [$labelStep] = $this->flowFor($seller, 'How I ship');
    $defaultFlow = $labelStep->fulfillmentFlow;
    $defaultFlow->update(['is_default' => true]);
    $second = FulfillmentFlow::factory()->create(['seller_id' => $seller->id, 'name' => 'Framed pieces']);
    $named = $this->listing($seller, ['fulfillment_flow_id' => $second->id, 'title' => 'Big Frame']);

    $response = $this->actingAs($seller, 'seller')->get('/seller/workflows');

    $response->assertOk();
    $response->assertSee('How I ship');
    $response->assertSee('Framed pieces');
    $response->assertSee('Default');
    $response->assertSee('Big Frame');
});

it('shows an empty state for a seller with no workflows yet', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/workflows');

    $response->assertOk();
    $response->assertSee('No workflows yet');
});

it('never shows another sellers workflows', function (): void {
    FulfillmentFlow::factory()->create(['seller_id' => $this->seller('Other Studio')->id, 'name' => 'Their flow']);

    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/workflows');

    $response->assertOk();
    $response->assertDontSee('Their flow');
});

it('renders the create form', function (): void {
    $response = $this->actingAs($this->seller(), 'seller')->get('/seller/workflows/create');

    $response->assertOk();
    $response->assertSee('New workflow');
});

it('creates a workflow, redirecting to its edit page', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->post('/seller/workflows', [
        'name' => 'Made to order',
        'steps' => [['id' => '', 'label' => 'Kiln cooled', 'action' => 'none', 'position' => 1]],
    ]);

    $flow = FulfillmentFlow::where('seller_id', $seller->id)->sole();
    $response->assertRedirect(route('seller.workflows.edit', $flow));
    $response->assertSessionHas('status', 'Workflow added.');
    expect($flow->name)->toBe('Made to order')
        ->and($flow->steps()->pluck('label')->all())->toBe(['Kiln cooled']);
});

it('makes a sellers first workflow the default, leaving a second off the role', function (): void {
    $seller = $this->seller();
    $this->actingAs($seller, 'seller')->post('/seller/workflows', ['name' => 'How I ship']);
    $this->actingAs($seller, 'seller')->post('/seller/workflows', ['name' => 'Framed pieces']);

    $flows = FulfillmentFlow::where('seller_id', $seller->id)->orderBy('name')->get();
    expect($flows->firstWhere('name', 'How I ship')?->is_default)->toBeTrue()
        ->and($flows->firstWhere('name', 'Framed pieces')?->is_default)->toBeFalse();
});

it('renders the edit form with the workflows name and steps', function (): void {
    $seller = $this->seller();
    $this->flowFor($seller, 'How Molly Ships');
    $flow = FulfillmentFlow::where('seller_id', $seller->id)->sole();

    $response = $this->actingAs($seller, 'seller')->get("/seller/workflows/{$flow->id}/edit");

    $response->assertOk();
    $response->assertSee('How Molly Ships');
    $response->assertSee('Label printed');
    $response->assertSee('Packed');
});

it('answers not found editing another sellers workflow', function (): void {
    $other = FulfillmentFlow::factory()->create(['seller_id' => $this->seller('Other Studio')->id]);

    $response = $this->actingAs($this->seller(), 'seller')->get("/seller/workflows/{$other->id}/edit");

    $response->assertNotFound();
});

it('saves an edit, redirecting back to the same edit page', function (): void {
    $seller = $this->seller();
    [$labelStep, $packStep] = $this->flowFor($seller, 'How Molly Ships');
    $flow = $labelStep->fulfillmentFlow;

    $response = $this->actingAs($seller, 'seller')->put("/seller/workflows/{$flow->id}", [
        'name' => 'How the Burrow Ships',
        'steps' => [
            ['id' => $labelStep->id, 'label' => 'Label printed', 'action' => 'print_label', 'position' => 1],
            ['id' => $packStep->id, 'label' => 'Carefully packed', 'action' => 'none', 'position' => 2],
        ],
    ]);

    $response->assertRedirect(route('seller.workflows.edit', $flow));
    $response->assertSessionHas('status', 'Workflow saved.');
    expect($flow->refresh()->name)->toBe('How the Burrow Ships');
});

it('never touches another sellers workflow on update', function (): void {
    $other = FulfillmentFlow::factory()->create(['seller_id' => $this->seller('Lovegood Curiosities')->id, 'name' => 'Theirs']);
    FulfillmentFlowStep::factory()->of($other, 0)->create();

    $response = $this->actingAs($this->seller(), 'seller')->put("/seller/workflows/{$other->id}", [
        'name' => 'Mine',
        'steps' => [],
    ]);

    $response->assertNotFound();
    expect($other->refresh()->name)->toBe('Theirs');
});

it('removes a workflow no listing names and that holds no default role', function (): void {
    $seller = $this->seller();
    $flow = FulfillmentFlow::factory()->create(['seller_id' => $seller->id]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/workflows/{$flow->id}");

    $response->assertRedirect(route('seller.workflows.index'));
    $response->assertSessionHas('status', 'Workflow removed.');
    expect(FulfillmentFlow::find($flow->id))->toBeNull();
});

it('refuses to remove the default workflow', function (): void {
    $seller = $this->seller();
    [$labelStep] = $this->flowFor($seller, 'How I ship');
    $flow = $labelStep->fulfillmentFlow;
    $flow->update(['is_default' => true]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/workflows/{$flow->id}");

    $response->assertSessionHasErrors();
    expect(FulfillmentFlow::find($flow->id))->not->toBeNull();
});

it('refuses to remove a workflow a listing names', function (): void {
    $seller = $this->seller();
    $flow = FulfillmentFlow::factory()->create(['seller_id' => $seller->id]);
    $this->listing($seller, ['fulfillment_flow_id' => $flow->id]);

    $response = $this->actingAs($seller, 'seller')->delete("/seller/workflows/{$flow->id}");

    $response->assertSessionHasErrors();
    expect(FulfillmentFlow::find($flow->id))->not->toBeNull();
});

it('answers not found removing another sellers workflow', function (): void {
    $other = FulfillmentFlow::factory()->create(['seller_id' => $this->seller('Other Studio')->id]);

    $response = $this->actingAs($this->seller(), 'seller')->delete("/seller/workflows/{$other->id}");

    $response->assertNotFound();
});

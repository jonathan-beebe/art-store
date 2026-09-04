<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Fulfillment\FlowStepDraft;
use App\Models\FulfillmentFlow;
use App\Models\FulfillmentFlowStep;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
$form = fn (array $overrides = []): array => $overrides + [
    'name' => 'How Molly Ships',
    'steps' => [
        ['id' => '', 'label' => 'Label printed', 'action' => 'print_label', 'position' => 1],
        ['id' => '', 'label' => 'Packed', 'action' => 'none', 'position' => 2],
    ],
];

it('refuses a flow that breaks a rule, leaving the sellers flow unchanged', function (array $overrides, string $field) use ($form): void {
    $seller = $this->seller();
    $this->actingAs($seller, 'seller')->put('/seller/orders/flow', $form());
    $flow = FulfillmentFlow::where('seller_id', $seller->id)->sole();
    $originalName = $flow->name;
    $originalLabels = $flow->load('steps')->steps->pluck('label')->all();

    $response = $this->actingAs($seller, 'seller')->put('/seller/orders/flow', $form($overrides));

    $response->assertSessionHasErrors($field);
    expect($flow->refresh()->name)->toBe($originalName)
        ->and($flow->load('steps')->steps->pluck('label')->all())->toBe($originalLabels);
})->with([
    'a blank name' => [['name' => ''], 'name'],
    'a name over 80 chars' => [['name' => str_repeat('a', 81)], 'name'],
    'more than 12 steps' => [
        ['steps' => array_map(
            fn (int $position): array => ['id' => '', 'label' => "Step {$position}", 'action' => 'none', 'position' => $position],
            range(1, 13),
        )],
        'steps',
    ],
    'a step label over the limit' => [
        ['steps' => [['id' => '', 'label' => str_repeat('a', FlowStepDraft::LABEL_LIMIT + 1), 'action' => 'none', 'position' => 1]]],
        'steps.0.label',
    ],
    'an unrecognised action value' => [
        ['steps' => [['id' => '', 'label' => 'Packed', 'action' => 'bogus', 'position' => 1]]],
        'steps.0.action',
    ],
    'a non-integer position' => [
        ['steps' => [['id' => '', 'label' => 'Packed', 'action' => 'none', 'position' => 'many']]],
        'steps.0.position',
    ],
]);

it('trims the name', function (): void {
    $request = UpdateFulfillmentFlowRequest::create('/seller/orders/flow', 'PUT', ['name' => '  How Molly Ships  ']);

    expect($request->name())->toBe('How Molly Ships');
});

it('orders the drafts by position, dropping removed and blank rows, and carries the ids of existing rows', function (): void {
    $request = UpdateFulfillmentFlowRequest::create('/seller/orders/flow', 'PUT', [
        'name' => 'How Molly Ships',
        'steps' => [
            ['id' => 'ffs_01J5X3M9A2K8YB7Q4R6T1V0PCK', 'label' => 'Packed', 'action' => 'none', 'position' => 2],
            ['id' => '', 'label' => 'Label printed', 'action' => 'print_label', 'position' => 1],
            ['id' => '', 'label' => '   ', 'action' => 'none', 'position' => 3],
            ['id' => 'ffs_01J5X3M9A2K8YB7Q4R6T1V0OLD', 'label' => 'Old step', 'action' => 'none', 'position' => 4, 'remove' => '1'],
        ],
    ]);

    $drafts = $request->drafts();

    expect($drafts)->toHaveCount(2)
        ->and($drafts[0]->id)->toBeNull()
        ->and($drafts[0]->label)->toBe('Label printed')
        ->and($drafts[1]->id)->toBe('ffs_01J5X3M9A2K8YB7Q4R6T1V0PCK')
        ->and($drafts[1]->label)->toBe('Packed');
});

it('refuses two rows naming the same step, leaving the flow whole', function () use ($form): void {
    $seller = $this->seller();
    $this->actingAs($seller, 'seller')->put('/seller/orders/flow', $form());
    $flow = FulfillmentFlow::where('seller_id', $seller->id)->sole();
    $labelStep = $flow->steps()->where('key', 'label-printed')->sole();

    $response = $this->actingAs($seller, 'seller')->put('/seller/orders/flow', [
        'name' => 'How Molly Ships',
        'steps' => [
            ['id' => $labelStep->id, 'label' => 'Label printed', 'action' => 'print_label', 'position' => 1],
            ['id' => $labelStep->id, 'label' => 'Label printed again', 'action' => 'none', 'position' => 2],
        ],
    ]);

    $response->assertSessionHasErrors('steps.1.id');
    expect($flow->steps()->count())->toBe(2);
});

it('refuses a row naming a step of another flow', function () use ($form): void {
    $seller = $this->seller();
    $this->actingAs($seller, 'seller')->put('/seller/orders/flow', $form());
    $flow = FulfillmentFlow::where('seller_id', $seller->id)->sole();

    $otherFlow = FulfillmentFlow::factory()->create(['seller_id' => $this->seller('Lovegood Curiosities')->id]);
    $otherStep = FulfillmentFlowStep::factory()->of($otherFlow, 0)->create();

    $response = $this->actingAs($seller, 'seller')->put('/seller/orders/flow', [
        'name' => 'How Molly Ships',
        'steps' => [['id' => $otherStep->id, 'label' => 'Borrowed', 'action' => 'none', 'position' => 1]],
    ]);

    $response->assertSessionHasErrors('steps.0.id');
    expect($flow->steps()->count())->toBe(2)
        ->and($flow->steps()->where('label', 'Borrowed')->count())->toBe(0);
});

it('refuses any step id from a seller who has no flow yet', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->put('/seller/orders/flow', [
        'name' => 'How Molly Ships',
        'steps' => [['id' => 'ffs_01J5X3M9A2K8YB7Q4R6T1V0WZE', 'label' => 'Packed', 'action' => 'none', 'position' => 1]],
    ]);

    $response->assertSessionHasErrors('steps.0.id');
    expect(FulfillmentFlow::count())->toBe(0);
});

it('takes a full flow plus the page\'s blank row', function (): void {
    $seller = $this->seller();
    $rows = array_map(
        fn (int $position): array => ['id' => '', 'label' => "Step {$position}", 'action' => 'none', 'position' => $position],
        range(1, UpdateFulfillmentFlowRequest::MAX_STEPS),
    );
    $rows[] = ['id' => '', 'label' => '', 'action' => 'none', 'position' => UpdateFulfillmentFlowRequest::MAX_STEPS + 1];

    $response = $this->actingAs($seller, 'seller')->put('/seller/orders/flow', ['name' => 'How Molly Ships', 'steps' => $rows]);

    $response->assertSessionHasNoErrors();
    expect(FulfillmentFlow::where('seller_id', $seller->id)->sole()->steps()->count())->toBe(UpdateFulfillmentFlowRequest::MAX_STEPS);
});

it('refuses more kept steps than a flow holds', function (): void {
    $seller = $this->seller();
    $rows = array_map(
        fn (int $position): array => ['id' => '', 'label' => "Step {$position}", 'action' => 'none', 'position' => $position],
        range(1, UpdateFulfillmentFlowRequest::MAX_STEPS + 1),
    );

    $response = $this->actingAs($seller, 'seller')->put('/seller/orders/flow', ['name' => 'How Molly Ships', 'steps' => $rows]);

    $response->assertSessionHasErrors('steps');
    expect(FulfillmentFlow::count())->toBe(0);
});

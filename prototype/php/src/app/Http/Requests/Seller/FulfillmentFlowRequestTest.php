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

it('refuses a workflow that breaks a rule, leaving it unchanged', function (array $overrides, string $field) use ($form): void {
    $seller = $this->seller();
    [$labelStep, $packStep] = $this->flowFor($seller, 'How Molly Ships');
    $flow = $labelStep->fulfillmentFlow;
    $originalName = $flow->name;
    $originalLabels = $flow->load('steps')->steps->pluck('label')->all();

    $response = $this->actingAs($seller, 'seller')->put("/seller/workflows/{$flow->id}", $form($overrides));

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
    $request = FulfillmentFlowRequest::create('/seller/workflows', 'POST', ['name' => '  How Molly Ships  ']);

    expect($request->name())->toBe('How Molly Ships');
});

it('orders the drafts by position, dropping removed and blank rows, and carries the ids of existing rows', function (): void {
    $request = FulfillmentFlowRequest::create('/seller/workflows/ffl_placeholder', 'PUT', [
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

it('refuses two rows naming the same step, leaving the workflow whole', function (): void {
    $seller = $this->seller();
    [$labelStep] = $this->flowFor($seller, 'How Molly Ships');
    $flow = $labelStep->fulfillmentFlow;

    $response = $this->actingAs($seller, 'seller')->put("/seller/workflows/{$flow->id}", [
        'name' => 'How Molly Ships',
        'steps' => [
            ['id' => $labelStep->id, 'label' => 'Label printed', 'action' => 'print_label', 'position' => 1],
            ['id' => $labelStep->id, 'label' => 'Label printed again', 'action' => 'none', 'position' => 2],
        ],
    ]);

    $response->assertSessionHasErrors('steps.1.id');
    expect($flow->steps()->count())->toBe(2);
});

it('refuses a row naming a step of another workflow', function (): void {
    $seller = $this->seller();
    [$labelStep] = $this->flowFor($seller, 'How Molly Ships');
    $flow = $labelStep->fulfillmentFlow;

    $otherFlow = FulfillmentFlow::factory()->create(['seller_id' => $this->seller('Lovegood Curiosities')->id]);
    $otherStep = FulfillmentFlowStep::factory()->of($otherFlow, 0)->create();

    $response = $this->actingAs($seller, 'seller')->put("/seller/workflows/{$flow->id}", [
        'name' => 'How Molly Ships',
        'steps' => [['id' => $otherStep->id, 'label' => 'Borrowed', 'action' => 'none', 'position' => 1]],
    ]);

    $response->assertSessionHasErrors('steps.0.id');
    expect($flow->steps()->count())->toBe(2)
        ->and($flow->steps()->where('label', 'Borrowed')->count())->toBe(0);
});

it('refuses any step id on a new workflow, since it names none yet', function (): void {
    $seller = $this->seller();

    $response = $this->actingAs($seller, 'seller')->post('/seller/workflows', [
        'name' => 'How Molly Ships',
        'steps' => [['id' => 'ffs_01J5X3M9A2K8YB7Q4R6T1V0WZE', 'label' => 'Packed', 'action' => 'none', 'position' => 1]],
    ]);

    $response->assertSessionHasErrors('steps.0.id');
    expect(FulfillmentFlow::count())->toBe(0);
});

it('takes a full workflow plus the page\'s blank row', function (): void {
    $seller = $this->seller();
    [$labelStep] = $this->flowFor($seller, 'How Molly Ships');
    $flow = $labelStep->fulfillmentFlow;
    $rows = array_map(
        fn (int $position): array => ['id' => '', 'label' => "Step {$position}", 'action' => 'none', 'position' => $position],
        range(1, FulfillmentFlowRequest::MAX_STEPS),
    );
    $rows[] = ['id' => '', 'label' => '', 'action' => 'none', 'position' => FulfillmentFlowRequest::MAX_STEPS + 1];

    $response = $this->actingAs($seller, 'seller')->put("/seller/workflows/{$flow->id}", ['name' => 'How Molly Ships', 'steps' => $rows]);

    $response->assertSessionHasNoErrors();
    expect($flow->steps()->count())->toBe(FulfillmentFlowRequest::MAX_STEPS);
});

it('refuses more kept steps than a workflow holds', function (): void {
    $seller = $this->seller();
    [$labelStep] = $this->flowFor($seller, 'How Molly Ships');
    $flow = $labelStep->fulfillmentFlow;
    $rows = array_map(
        fn (int $position): array => ['id' => '', 'label' => "Step {$position}", 'action' => 'none', 'position' => $position],
        range(1, FulfillmentFlowRequest::MAX_STEPS + 1),
    );

    $response = $this->actingAs($seller, 'seller')->put("/seller/workflows/{$flow->id}", ['name' => 'How Molly Ships', 'steps' => $rows]);

    $response->assertSessionHasErrors('steps');
    expect($flow->steps()->count())->toBe(2);
});

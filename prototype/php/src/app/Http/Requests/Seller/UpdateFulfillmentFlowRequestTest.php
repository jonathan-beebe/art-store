<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Fulfillment\FlowStepDraft;
use App\Models\FulfillmentFlow;

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

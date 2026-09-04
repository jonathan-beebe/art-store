<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Fulfillment\FlowStepAction;
use App\Domain\Fulfillment\FlowStepDraft;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The whole flow arrives as one form: a name and a list of rows. A row the
 * seller ticked for removal and a row with no words are both left out — the
 * second is the empty row the page carries for adding a step. The rest are
 * ordered by the number the seller typed against them.
 *
 * @phpstan-type StepRow array{id: ?string, label: string, action: FlowStepAction, position: int, remove: bool}
 */
final class UpdateFulfillmentFlowRequest extends FormRequest
{
    public const int MAX_STEPS = 12;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'steps' => ['nullable', 'array', 'max:'.self::MAX_STEPS],
            'steps.*.id' => ['nullable', 'string', 'size:30'],
            'steps.*.label' => ['nullable', 'string', 'max:'.FlowStepDraft::LABEL_LIMIT],
            'steps.*.action' => ['nullable', Rule::enum(FlowStepAction::class)],
            'steps.*.position' => ['nullable', 'integer', 'min:1', 'max:99'],
            'steps.*.remove' => ['nullable', 'boolean'],
        ];
    }

    public function name(): string
    {
        return $this->string('name')->trim()->toString();
    }

    /**
     * @return list<FlowStepDraft>
     */
    public function drafts(): array
    {
        $rows = array_values(array_filter(
            $this->rows(),
            fn (array $row): bool => ! $row['remove'] && $row['label'] !== '',
        ));

        usort($rows, fn (array $left, array $right): int => $left['position'] <=> $right['position']);

        return array_map(
            fn (array $row): FlowStepDraft => FlowStepDraft::of($row['id'], $row['label'], $row['action']),
            $rows,
        );
    }

    /**
     * @return list<StepRow>
     */
    private function rows(): array
    {
        $submitted = $this->input('steps');
        $submitted = is_array($submitted) ? array_values($submitted) : [];

        return array_map($this->row(...), $submitted, array_keys($submitted));
    }

    /**
     * @return StepRow
     */
    private function row(mixed $submitted, int $index): array
    {
        $row = is_array($submitted) ? $submitted : [];
        $id = $row['id'] ?? null;
        $label = $row['label'] ?? null;
        $action = $row['action'] ?? null;
        $position = $row['position'] ?? null;

        return [
            'id' => is_string($id) && $id !== '' ? $id : null,
            'label' => is_string($label) ? trim($label) : '',
            'action' => (is_string($action) ? FlowStepAction::tryFrom($action) : null) ?? FlowStepAction::None,
            'position' => is_numeric($position) ? (int) $position : $index + 1,
            'remove' => filter_var($row['remove'] ?? false, FILTER_VALIDATE_BOOL),
        ];
    }
}

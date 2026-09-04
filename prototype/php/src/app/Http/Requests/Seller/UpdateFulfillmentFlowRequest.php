<?php

declare(strict_types=1);

namespace App\Http\Requests\Seller;

use App\Domain\Fulfillment\FlowStepAction;
use App\Domain\Fulfillment\FlowStepDraft;
use App\Models\FulfillmentFlow;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * The whole flow arrives as one form: a name and a list of rows. A row the
 * seller ticked for removal and a row with no words are both left out — the
 * second is the empty row the page carries for adding a step. The rest are
 * ordered by the number the seller typed against them.
 *
 * A row that names a step names one of this flow's own, and names it once:
 * two rows carrying one id would read as a step kept and a step dropped, and
 * an id from another flow would silently mint a new step.
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
            // The page carries one blank row for adding a step, so a full
            // flow submits MAX_STEPS + 1 rows. What the seller kept is
            // counted in `after()`, once the blanks are dropped.
            'steps' => ['nullable', 'array', 'max:'.(self::MAX_STEPS + 1)],
            'steps.*.id' => $this->stepIdRules(),
            'steps.*.label' => ['nullable', 'string', 'max:'.FlowStepDraft::LABEL_LIMIT],
            'steps.*.action' => ['nullable', Rule::enum(FlowStepAction::class)],
            'steps.*.position' => ['nullable', 'integer', 'min:1', 'max:99'],
            'steps.*.remove' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->keptCount() > self::MAX_STEPS) {
                $validator->errors()->add('steps', 'A flow holds at most '.self::MAX_STEPS.' steps.');
            }
        }];
    }

    /**
     * A blank id is the page saying "this row is new", which `distinct` and
     * `exists` both have to read as absent rather than as a value.
     */
    protected function prepareForValidation(): void
    {
        $submitted = $this->input('steps');

        if (! is_array($submitted)) {
            return;
        }

        $this->merge(['steps' => array_map($this->withBlankIdRemoved(...), array_values($submitted))]);
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
        $rows = $this->keptRows();

        usort($rows, fn (array $left, array $right): int => $left['position'] <=> $right['position']);

        return array_map(
            fn (array $row): FlowStepDraft => FlowStepDraft::of($row['id'], $row['label'], $row['action']),
            $rows,
        );
    }

    /**
     * @return list<string>
     */
    private function stepIdRules(): array
    {
        $flowId = $this->defaultFlowId();

        // A seller with no flow yet has no step to name.
        if ($flowId === null) {
            return ['prohibited'];
        }

        return [
            'nullable',
            'string',
            'size:30',
            'distinct',
            (string) Rule::exists('fulfillment_flow_steps', 'id')->where('fulfillment_flow_id', $flowId),
        ];
    }

    private function defaultFlowId(): ?string
    {
        $seller = $this->user('seller') ?? throw new RuntimeException('The flow editor runs behind the auth.seller middleware.');

        $flowId = FulfillmentFlow::query()->where('seller_id', $seller->getAuthIdentifier())->defaults()->value('id');

        return is_string($flowId) ? $flowId : null;
    }

    private function keptCount(): int
    {
        return count($this->keptRows());
    }

    /**
     * @return list<StepRow>
     */
    private function keptRows(): array
    {
        return array_values(array_filter(
            $this->rows(),
            fn (array $row): bool => ! $row['remove'] && $row['label'] !== '',
        ));
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

    /**
     * @return array<mixed>
     */
    private function withBlankIdRemoved(mixed $submitted): array
    {
        $row = is_array($submitted) ? $submitted : [];
        $id = $row['id'] ?? null;

        if (is_string($id) && trim($id) === '') {
            unset($row['id']);
        }

        return $row;
    }
}

<?php

declare(strict_types=1);

namespace App\Actions\Fulfillment;

use App\Domain\Fulfillment\DefaultFlow;
use App\Domain\Fulfillment\FlowStepDraft;
use App\Models\FulfillmentFlow;
use App\Models\FulfillmentFlowStep;
use App\Models\Seller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Writes a seller's default flow from the list they submitted: the rows they
 * kept stay the rows they were, the ones they left out go, and the order is
 * the order they sent. A step a draft names by id keeps its key through a
 * rename, so the events pointing at it keep pointing at it.
 */
final readonly class SaveFulfillmentFlow
{
    private const int KEY_LIMIT = 36;

    /**
     * Positions the rewrite parks a surviving step at while it fills the
     * range from zero. `fulfillment_flow_steps` is unique on
     * (fulfillment_flow_id, position) and SQLite judges that row by row, so
     * a reorder writing a position another row still holds is refused.
     */
    private const int PARKED_POSITION = -1;

    /**
     * @param  list<FlowStepDraft>  $drafts
     */
    public function __invoke(Seller $seller, string $name, array $drafts): FulfillmentFlow
    {
        return DB::transaction(function () use ($seller, $name, $drafts): FulfillmentFlow {
            $flow = $this->defaultFlowOf($seller);
            $flow->update(['name' => $name]);

            $this->removeStepsLeftOut($flow, $drafts);
            $this->parkPositions($flow);
            $this->writeSteps($flow, $drafts);

            return $flow->load('steps');
        });
    }

    private function defaultFlowOf(Seller $seller): FulfillmentFlow
    {
        return FulfillmentFlow::firstOrCreate(
            ['seller_id' => $seller->id, 'is_default' => true],
            ['name' => DefaultFlow::NAME],
        );
    }

    /**
     * @param  list<FlowStepDraft>  $drafts
     */
    private function removeStepsLeftOut(FulfillmentFlow $flow, array $drafts): void
    {
        $kept = array_values(array_filter(
            array_map(fn (FlowStepDraft $draft): ?string => $draft->id, $drafts),
            is_string(...),
        ));

        $flow->steps()->whereNotIn('id', $kept)->delete();
    }

    private function parkPositions(FulfillmentFlow $flow): void
    {
        foreach ($flow->steps()->get() as $index => $step) {
            $step->update(['position' => self::PARKED_POSITION - $index]);
        }
    }

    /**
     * @param  list<FlowStepDraft>  $drafts
     */
    private function writeSteps(FulfillmentFlow $flow, array $drafts): void
    {
        $surviving = $flow->steps()->get()->keyBy('id');
        $taken = array_values($surviving->map(fn (FulfillmentFlowStep $step): string => $step->key)->all());

        foreach ($drafts as $position => $draft) {
            $step = $draft->isNew() ? null : $surviving->get($draft->id);

            if ($step instanceof FulfillmentFlowStep) {
                $step->update(['label' => $draft->label, 'action' => $draft->action, 'position' => $position]);

                continue;
            }

            $taken[] = $key = $this->keyFor($draft->label, $taken);

            $flow->steps()->create([
                'seller_id' => $flow->seller_id,
                'key' => $key,
                'label' => $draft->label,
                'action' => $draft->action,
                'position' => $position,
            ]);
        }
    }

    /**
     * A handle spelled from the step's words, and a numbered one when the
     * flow already answers to that spelling.
     *
     * @param  list<string>  $taken
     */
    private function keyFor(string $label, array $taken): string
    {
        $base = Str::limit(Str::slug($label), self::KEY_LIMIT, '');
        $base = $base === '' ? 'step' : $base;
        $key = $base;

        for ($suffix = 2; in_array($key, $taken, true); $suffix++) {
            $key = $base.'-'.$suffix;
        }

        return $key;
    }
}

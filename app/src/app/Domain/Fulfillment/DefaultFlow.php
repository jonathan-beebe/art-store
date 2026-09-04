<?php

declare(strict_types=1);

namespace App\Domain\Fulfillment;

/**
 * The flow every seller starts with: print the label, pack the parcel.
 */
final class DefaultFlow
{
    public const string NAME = 'How I ship';

    private function __construct() {} // @codeCoverageIgnore

    /**
     * @return list<FlowStepDraft>
     */
    public static function drafts(): array
    {
        return [
            FlowStepDraft::of(null, 'Label printed', FlowStepAction::PrintLabel),
            FlowStepDraft::of(null, 'Packed', FlowStepAction::None),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Domain\Analytics\RangeChange;

/**
 * One funnel's tile on the analytics home: its end-to-end conversion for
 * the range and the change in its last step's own count against the range
 * before. `$funnelId` links the tile to {@see \App\Http\Controllers\Admin\Analytics\FunnelController::show()}.
 */
final readonly class FunnelTile
{
    public function __construct(
        public string $funnelId,
        public string $name,
        public string $conversionText,
        public RangeChange $change,
    ) {}
}

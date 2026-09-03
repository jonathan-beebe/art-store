<?php

declare(strict_types=1);

namespace App\Analytics\Admin;

use App\Domain\Analytics\AnalyticsRange;
use App\Domain\Analytics\FunnelDefinition;
use App\Domain\Analytics\FunnelRate;
use App\Models\Funnel as FunnelModel;
use Illuminate\Support\Collection;

/**
 * One tile per admin-defined funnel, in position order, for the analytics
 * home. Reads the funnel rows in one query, then one {@see Funnel} query
 * per tile — the home never issues a query per step, only per funnel.
 * Capped at eight: a row wider than that reads as a list, not a row of
 * tiles, and `admin.funnels.index` is where the rest of them live.
 */
final class FunnelTiles
{
    private const int LIMIT = 8;

    /**
     * @return list<FunnelTile>
     */
    public static function forRange(AnalyticsRange $range): array
    {
        /** @var Collection<int, FunnelModel> $funnels */
        $funnels = FunnelModel::query()->orderBy('position')->orderBy('id')->limit(self::LIMIT)->get();

        return array_values($funnels->map(fn (FunnelModel $funnel): FunnelTile => self::tile($funnel, $range))->all());
    }

    private static function tile(FunnelModel $funnel, AnalyticsRange $range): FunnelTile
    {
        $steps = Funnel::forRange(FunnelDefinition::of($funnel->steps), $range)->steps;
        $first = $steps[0];
        $last = $steps[count($steps) - 1];

        return new FunnelTile($funnel->id, $funnel->name, self::conversionText($first->current, $last->current), $last->change);
    }

    /**
     * The last step's sessions as a share of visitors — "—" rather than a
     * division when the range held no visitors at all.
     */
    private static function conversionText(int $visitors, int $lastStepCurrent): string
    {
        if ($visitors === 0) {
            return '—';
        }

        $rate = FunnelRate::of($lastStepCurrent, $visitors, 'visitors');

        return $rate === null ? '0%' : $rate->text;
    }
}

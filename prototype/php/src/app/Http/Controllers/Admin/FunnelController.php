<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Domain\Analytics\AnalyticsEventName;
use App\Domain\Analytics\FunnelDefinition;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FunnelRequest;
use App\Models\Funnel;
use App\Support\Admin\FunnelStepListOp;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * `/admin/funnels`: an admin names a funnel's steps, in order, from the
 * analytics vocabulary. `store()`/`update()` also carry the editor's "Add
 * step", "Remove", "Move up", and "Move down" buttons — every one of them
 * posts back to the same route with an `op` naming the action, and gets
 * the form re-rendered with the working step list rather than saved; only
 * `op=save` (the form's default) validates a complete funnel and persists.
 */
final class FunnelController extends Controller
{
    public function index(): View
    {
        $funnels = Funnel::query()->orderBy('position')->orderBy('id')->get();

        return view('admin.funnels.index', [
            'funnels' => $funnels,
            'stepChains' => $funnels->mapWithKeys(fn (Funnel $funnel): array => [$funnel->id => self::stepChain($funnel)]),
        ]);
    }

    public function create(): View
    {
        return view('admin.funnels.create', self::editorData(
            self::oldName(''),
            self::oldSteps(['', '']),
        ));
    }

    public function store(FunnelRequest $request): RedirectResponse|View
    {
        if (! $request->isSave()) {
            return view('admin.funnels.create', self::editorData($request->name(), self::nextSteps($request)));
        }

        Funnel::query()->create([
            'name' => $request->name(),
            'slug' => $request->slug(),
            'steps' => self::stepValues($request->definition()),
            'position' => self::nextPosition(),
        ]);

        return redirect()->route('admin.funnels.index')->with('status', 'Funnel created.');
    }

    public function edit(Funnel $funnel): View
    {
        return view('admin.funnels.edit', [
            'funnel' => $funnel,
            ...self::editorData(self::oldName($funnel->name), self::oldSteps($funnel->steps)),
        ]);
    }

    public function update(FunnelRequest $request, Funnel $funnel): RedirectResponse|View
    {
        if (! $request->isSave()) {
            return view('admin.funnels.edit', [
                'funnel' => $funnel,
                ...self::editorData($request->name(), self::nextSteps($request)),
            ]);
        }

        $funnel->update([
            'name' => $request->name(),
            'slug' => $request->slug(),
            'steps' => self::stepValues($request->definition()),
        ]);

        return redirect()->route('admin.funnels.index')->with('status', 'Funnel updated.');
    }

    public function destroy(Funnel $funnel): RedirectResponse
    {
        $funnel->delete();

        return redirect()->route('admin.funnels.index')->with('status', 'Funnel deleted.');
    }

    private static function stepChain(Funnel $funnel): string
    {
        return collect($funnel->steps())
            ->map(fn (AnalyticsEventName $name): string => $name->pluralLabel())
            ->implode(' → ');
    }

    /**
     * @param  list<string>  $steps
     * @return array{name: string, steps: list<string>, eventNames: list<AnalyticsEventName>}
     */
    private static function editorData(string $name, array $steps): array
    {
        return [
            'name' => $name,
            'steps' => $steps,
            'eventNames' => AnalyticsEventName::cases(),
        ];
    }

    /**
     * The name a `save`-bound render falls back on absent flashed input —
     * blank on first load of the create page, the model's own name on
     * first load of the edit page.
     */
    private static function oldName(string $fallback): string
    {
        $name = old('name', $fallback);

        return is_string($name) ? $name : $fallback;
    }

    /**
     * The step list a `save`-bound render falls back on absent flashed
     * input — the model's own steps on first load of the edit page, two
     * blank rows on first load of the create page.
     *
     * @param  list<string>  $fallback
     * @return list<string>
     */
    private static function oldSteps(array $fallback): array
    {
        $steps = old('steps', $fallback);

        if (! is_array($steps)) {
            return $fallback;
        }

        return array_values(array_filter($steps, 'is_string'));
    }

    /**
     * @return list<string>
     */
    private static function nextSteps(FunnelRequest $request): array
    {
        return FunnelStepListOp::apply($request->stepNames(), $request->op());
    }

    private static function nextPosition(): int
    {
        $highest = Funnel::query()->max('position');

        return (is_int($highest) ? $highest : 0) + 1;
    }

    /**
     * @return list<string>
     */
    private static function stepValues(FunnelDefinition $definition): array
    {
        return array_map(fn (AnalyticsEventName $name): string => $name->value, $definition->steps);
    }
}

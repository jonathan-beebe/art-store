<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/**
 * Every non-abstract, non-interface, non-enum, non-trait class under app/
 * sits beside a <Name>Test.php sidecar, unless it is listed below as an
 * exception: a class covered by another file's tests, or one with no
 * independently testable behavior. An exception entry whose sidecar now
 * exists is stale and must be removed, so the list can only shrink.
 */
it('gives every class under app a sidecar test', function (): void {
    /** @var array<string, string> $exceptions */
    $exceptions = [
        // Overrides two protected framework hooks with no logic of its own
        // (see its docblock); routes/consoleTest.php covers the schedule
        // load it keeps, and every Console\Commands sidecar test passing at
        // all covers the Finder scan it drops.
        'app/Console/Kernel.php' => 'covered by routes/consoleTest.php and the Console\Commands sidecar tests',
        // Relations-and-casts-only models: nothing beyond FK resolution to
        // pin. Their tables' invariants live in the related models' tests.
        'app/Models/CategoryProperty.php' => 'relations and casts only; the category/property linkage is exercised through CategoryTest and ListingAttributeTest',
        'app/Models/ModifierScope.php' => 'relations and casts only; scoping behavior is pinned by SetModifierScopeTest and ModifierTest',
        'app/Models/OptionAxis.php' => 'relations and casts only; axis behavior is pinned by the configurator action and domain tests',
        'app/Models/PropertyValue.php' => 'relations and casts only; exercised through ListingAttributeTest and the taxonomy seeder tests',
        // Plain value carriers with no logic of their own, built and asserted
        // through the classes that produce them.
        'app/Domain/Configurator/AxisDefaults.php' => 'value carrier; exercised through AxisSelectionResolverTest and ConfiguratorPageResolverTest',
        'app/Domain/Configurator/VariantSnapshot.php' => 'value carrier; exercised through VariantAvailabilityTest and the resolver tests',
        'app/Domain/Orders/BlockedLine.php' => 'value carrier; exercised through OrderPlacementPlanTest',
        'app/Domain/Orders/PlaceableLine.php' => 'value carrier; exercised through OrderPlacementPlanTest and PlaceableLineBuilderTest',
        'app/Analytics/ListingEventCounts.php' => 'value carrier; exercised through AnalyticsReportTest',
        'app/Analytics/AnalyticsEventRow.php' => 'value carrier; exercised through AnalyticsReportTest',
        'app/Analytics/Admin/EventTotal.php' => 'value carrier; exercised through EventTotalsTest',
        'app/Analytics/Admin/ActorSummary.php' => 'value carrier; exercised through ActorAggregatesTest',
        'app/Analytics/Admin/ActorsPage.php' => 'value carrier; exercised through ActorListTest',
        'app/Analytics/Admin/Jump.php' => 'value carrier; exercised through AnalyticsJumpTest',
        'app/Analytics/Admin/EventTile.php' => 'value carrier; exercised through EventDetailTest',
        'app/Analytics/Admin/EventBreakdownRow.php' => 'value carrier; exercised through EventDetailTest',
        'app/Analytics/Admin/EventDetailView.php' => 'value carrier; exercised through EventDetailTest',
        'app/Logging/Admin/LogRequestGroup.php' => 'plain DTO; built and asserted through LogRowQueryTest',
        'app/Support/Configurator/ListingConfiguration.php' => 'plain DTO; built from real listings and asserted through ConfiguratorPageResolverTest',
    ];

    $base = dirname(__DIR__);

    $missing = [];

    foreach (Finder::create()->files()->name('*.php')->notName('*Test.php')->in($base.'/app') as $file) {
        $contents = $file->getContents();

        $isInterface = preg_match('/^\s*(final\s+)?interface\s+\w+/m', $contents) === 1;
        $isEnum = preg_match('/^\s*enum\s+\w+/m', $contents) === 1;
        $isAbstract = preg_match('/^\s*abstract\s+class\s+\w+/m', $contents) === 1;
        $isTrait = preg_match('/^\s*trait\s+\w+/m', $contents) === 1;
        $isClass = preg_match('/^\s*(final\s+)?(readonly\s+)?class\s+\w+/m', $contents) === 1;

        if (! $isClass || $isInterface || $isEnum || $isAbstract || $isTrait) {
            continue;
        }

        $sidecar = substr($file->getPathname(), 0, -4).'Test.php';

        if (file_exists($sidecar)) {
            continue;
        }

        $relative = 'app/'.ltrim(str_replace($base.'/app', '', $file->getPathname()), '/');

        if (! array_key_exists($relative, $exceptions)) {
            $missing[] = $relative;
        }
    }

    expect($missing)->toBe([]);

    $stale = [];

    foreach (array_keys($exceptions) as $relative) {
        $sidecar = substr($base.'/'.$relative, 0, -4).'Test.php';

        if (file_exists($sidecar)) {
            $stale[] = $relative;
        }
    }

    expect($stale)->toBe([]);
});

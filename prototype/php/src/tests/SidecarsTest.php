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

<?php

declare(strict_types=1);

namespace App\Domain\Payments;

/*
|--------------------------------------------------------------------------
| preg_replace() override
|--------------------------------------------------------------------------
|
| PHP resolves an unqualified function call from the innermost namespace
| first, so this shadows the built-in for FakeCard alone: normally it
| forwards to the real preg_replace(), letting FakeCardTest force the null
| result the real function can return but the suite cannot trigger with an
| ordinary card number.
|
| Declared here, in a Composer `autoload-dev` "files" entry (composer.json),
| rather than in FakeCardTest.php itself: PHP caches which function an
| unqualified call resolves to at that call site's first execution, for the
| life of the process. Under Pest's `--parallel` worker, which reuses one
| process across many test files, some other test's ordinary card decision
| can call `FakeCard::decide()` — and cache the real `preg_replace()` at
| that call site — before Pest ever requires FakeCardTest.php. A file
| Composer requires while building the autoloader, before any test file
| loads, closes that race by declaring the override first, always.
|
*/
$GLOBALS['fakeCardForcePregReplaceNull'] = false;

function preg_replace(string $pattern, string $replacement, string $subject): ?string
{
    return $GLOBALS['fakeCardForcePregReplaceNull'] ? null : \preg_replace($pattern, $replacement, $subject);
}

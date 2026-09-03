<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Logging\LogStore;
use Tests\CommerceTestCase;
use Tests\LogViewerFixtures as Fixtures;

/**
 * Every `<form method="$method" ...>...</form>` block's inner HTML on a
 * page — the boundary these tests reason about, since payouts carries a GET
 * filter form and a second POST form beside it (and every admin page's
 * layout carries two more POST forms of its own, desktop and mobile
 * "Sign out").
 *
 * @return list<string>
 */
function adminFormBlocks(string $html, string $method): array
{
    preg_match_all('/<form\s+method="'.$method.'"[^>]*>(.*?)<\/form>/s', $html, $matches);

    return $matches[1];
}

/**
 * The one form fragment, among $forms, that contains $needle — how these
 * tests pick the payouts "Run weekly payout" form out from the layout's
 * sign-out forms, all three being `method="POST"`.
 *
 * @param  list<string>  $forms
 */
function adminFormContaining(array $forms, string $needle): string
{
    $matches = array_values(array_filter($forms, fn (string $form): bool => str_contains($form, $needle)));

    expect($matches)->toHaveCount(1, "expected exactly one form containing {$needle}");

    return $matches[0];
}

/**
 * Every `class="..."` value among the given tag-attribute strings (each the
 * captured attribute list of one opening tag).
 *
 * @param  list<string>  $attributeStrings
 * @return list<string>
 */
function adminClassAttributes(array $attributeStrings): array
{
    $classes = [];

    foreach ($attributeStrings as $attributes) {
        if (preg_match('/class="([^"]*)"/', $attributes, $classMatch)) {
            $classes[] = $classMatch[1];
        }
    }

    return $classes;
}

/**
 * Every `<select ...>` class attribute inside an HTML fragment.
 *
 * @return list<string>
 */
function adminSelectClasses(string $html): array
{
    preg_match_all('/<select\s([^>]*)>/', $html, $matches);

    return adminClassAttributes($matches[1]);
}

/**
 * Every `<input type="text">` / `<input type="date">` class attribute
 * inside an HTML fragment — the two field types the admin filter forms use
 * (search boxes and the payout settle-date).
 *
 * @return list<string>
 */
function adminTextInputClasses(string $html): array
{
    preg_match_all('/<input\s([^>]*)>/', $html, $matches);

    $classes = [];

    foreach ($matches[1] as $attributes) {
        if (preg_match('/type="(text|date)"/', $attributes) === 1 && preg_match('/class="([^"]*)"/', $attributes, $classMatch)) {
            $classes[] = $classMatch[1];
        }
    }

    return $classes;
}

/**
 * Every `<button type="submit">` class attribute inside an HTML fragment,
 * optionally narrowed to buttons whose visible text is one of $labels.
 *
 * @param  list<string>  $labels
 * @return list<string>
 */
function adminSubmitButtonClasses(string $html, array $labels = []): array
{
    preg_match_all('/<button\s([^>]*)>(.*?)<\/button>/s', $html, $matches, PREG_SET_ORDER);

    $classes = [];

    foreach ($matches as $match) {
        [, $attributes, $text] = $match;

        if (! str_contains($attributes, 'type="submit"')) {
            continue;
        }

        if ($labels !== [] && ! in_array(trim($text), $labels, true)) {
            continue;
        }

        if (preg_match('/class="([^"]*)"/', $attributes, $classMatch)) {
            $classes[] = $classMatch[1];
        }
    }

    return $classes;
}

/**
 * Every `<a ...>Clear</a>` class attribute inside an HTML fragment.
 *
 * @return list<string>
 */
function adminClearLinkClasses(string $html): array
{
    preg_match_all('/<a\s([^>]*)>(.*?)<\/a>/s', $html, $matches, PREG_SET_ORDER);

    $classes = [];

    foreach ($matches as $match) {
        [, $attributes, $text] = $match;

        if (trim($text) !== 'Clear') {
            continue;
        }

        if (preg_match('/class="([^"]*)"/', $attributes, $classMatch)) {
            $classes[] = $classMatch[1];
        }
    }

    return $classes;
}

/**
 * The `<summary>...More filters...</summary>` class attribute inside an
 * HTML fragment, if present.
 */
function adminMoreFiltersSummaryClass(string $html): ?string
{
    if (! preg_match('/<summary\s([^>]*)>(.*?)<\/summary>/s', $html, $match)) {
        return null;
    }

    if (! str_contains($match[2], 'More filters')) {
        return null;
    }

    return preg_match('/class="([^"]*)"/', $match[1], $classMatch) === 1 ? $classMatch[1] : null;
}

/**
 * The seven admin pages whose filter row this file reasons about: the six
 * `x-admin.filters` consumers plus the log viewer's own header form.
 * Rendered once per test as a real admin request. The logs page needs a
 * bound `LogStore` — the suite runs with `LOG_DATABASE_FILE` off, so
 * `/admin/logs` renders its "unavailable" state (no form at all) without
 * one; an empty store is enough since the Event/Phase options come from
 * enums, not from stored lines.
 *
 * @return array<string, string>
 */
function adminFilterPages(CommerceTestCase $test): array
{
    $admin = $test->admin();
    app()->instance(LogStore::class, Fixtures::store([]));

    $get = fn (string $uri): string => (string) $test->actingAs($admin, 'admin')->get($uri)->getContent();

    return [
        'orders' => $get('/admin/orders'),
        'fulfillments' => $get('/admin/fulfillments'),
        'listings' => $get('/admin/listings'),
        'customers' => $get('/admin/customers'),
        'ledger' => $get('/admin/ledger'),
        'payouts' => $get('/admin/payouts'),
        'logs' => $get('/admin/logs?domain='),
    ];
}

it('renders exactly one GET filter form per page, plus a POST form on payouts', function (): void {
    $pages = adminFilterPages($this);

    foreach ($pages as $page => $html) {
        expect(adminFormBlocks($html, 'GET'))->toHaveCount(1, "expected one GET filter form on {$page}");
    }

    adminFormContaining(adminFormBlocks($pages['payouts'], 'POST'), 'name="as_of"');
});

it('renders one select idiom across every admin filter form', function (): void {
    $pages = adminFilterPages($this);

    $classes = [];
    $countsByPage = [];

    foreach ($pages as $page => $html) {
        foreach (adminFormBlocks($html, 'GET') as $form) {
            $found = adminSelectClasses($form);
            $countsByPage[$page] = ($countsByPage[$page] ?? 0) + count($found);
            array_push($classes, ...$found);
        }
    }

    // orders (status, customer) + fulfillments (status, seller) + listings
    // (status, seller, removed) + customers (standing) + ledger (seller,
    // type) + payouts (seller) + logs (event, phase) = 13 selects across
    // the seven pages' GET forms.
    expect($classes)->toHaveCount(13, 'implementer note: selects found per page — '.json_encode($countsByPage));

    // Fails today: the ring idiom (seller/customer/status/removed), the
    // border idiom (type/standing), the logs Event select's own sizing,
    // and the logs Phase select's own sizing are four distinct class
    // lists, not one.
    expect(array_values(array_unique($classes)))->toHaveCount(1);
});

it('renders one text/date-input idiom across the admin filter forms that carry one', function (): void {
    $pages = adminFilterPages($this);

    $classes = [];

    foreach ($pages as $html) {
        foreach (adminFormBlocks($html, 'GET') as $form) {
            array_push($classes, ...adminTextInputClasses($form));
        }
    }

    $payoutForm = adminFormContaining(adminFormBlocks($pages['payouts'], 'POST'), 'name="as_of"');
    array_push($classes, ...adminTextInputClasses($payoutForm));

    // The payouts settle-date (1) plus the logs "More filters" panel's nine
    // text inputs (request, txn, session, actor, msg, from, to, key,
    // value) — the six other GET forms carry only selects.
    expect($classes)->toHaveCount(10);

    // Fails today: the payout date input is still on the bordered idiom
    // (`rounded border border-stone-400 ... px-3 py-2`) while the logs
    // inputs are already on the ring idiom (`border-0 ... inset-ring`) —
    // two distinct class lists, not one.
    expect(array_values(array_unique($classes)))->toHaveCount(1);
});

it('renders one primary-button treatment for Filter, Apply filters, and the payouts submit', function (): void {
    $pages = adminFilterPages($this);

    $classes = [];

    foreach ($pages as $html) {
        foreach (adminFormBlocks($html, 'GET') as $form) {
            array_push($classes, ...adminSubmitButtonClasses($form, ['Filter', 'Apply filters']));
        }
    }

    $payoutForm = adminFormContaining(adminFormBlocks($pages['payouts'], 'POST'), 'name="as_of"');
    array_push($classes, ...adminSubmitButtonClasses($payoutForm));

    // Six x-admin.filters "Filter" buttons + logs' "Filter" and "Apply
    // filters" + the payouts "Run weekly payout" submit = 9.
    expect($classes)->toHaveCount(9);

    // Fails today: the six x-admin.filters buttons, the two (identical)
    // logs buttons, and the payouts submit are three distinct class lists.
    expect(array_values(array_unique($classes)))->toHaveCount(1);

    foreach (array_unique($classes) as $class) {
        // Fails today: the logs buttons are still `bg-stone-900` (the
        // pre-stone-chrome inverted primary), not the admin's actual
        // primary `bg-stone-700`.
        expect($class)->toContain('bg-stone-700');
    }
});

it('renders one secondary treatment for every Clear link', function (): void {
    $pages = adminFilterPages($this);

    $classes = [];

    foreach ($pages as $html) {
        foreach (adminFormBlocks($html, 'GET') as $form) {
            array_push($classes, ...adminClearLinkClasses($form));
        }
    }

    // Six x-admin.filters Clear links + logs' two (one in the More-filters
    // panel, one beside Filter) = 8.
    expect($classes)->toHaveCount(8);

    expect(array_values(array_unique($classes)))->toHaveCount(1);

    // The logs page is the only one of the seven with a second candidate
    // secondary control ("More filters"), and no other page has anything
    // to compare it against — its class list is left as the implementer's
    // choice rather than pinned to the Clear links here.
    expect(adminMoreFiltersSummaryClass($pages['logs']))->not->toBeNull();
});

/**
 * The vertical sizing tokens (`min-h-*`, `sm:min-h-*`, `py-*`, and any
 * other breakpoint-prefixed variant of either) carried by a set of class
 * attribute values — HTML carries no rendered height to measure, so this
 * is the proxy the tap-target test compares instead: two controls sized by
 * the same tokens render the same height by construction.
 *
 * @param  list<string>  $classAttributes
 * @return list<string>
 */
function adminVerticalSizingTokens(array $classAttributes): array
{
    $tokens = [];

    foreach ($classAttributes as $classes) {
        $words = preg_split('/\s+/', trim($classes));

        foreach ($words === false ? [] : $words as $token) {
            if ($token !== '' && preg_match('/^(?:[a-z]+:)?(?:min-h-|py-)/', $token) === 1) {
                $tokens[] = $token;
            }
        }
    }

    return array_values(array_unique($tokens));
}

it('gives every submit the same vertical sizing tokens as the selects beside it, and a 44px floor', function (): void {
    $pages = adminFilterPages($this);

    foreach ($pages as $page => $html) {
        foreach (adminFormBlocks($html, 'GET') as $form) {
            $selects = adminSelectClasses($form);

            if ($selects === []) {
                continue;
            }

            $selectTokens = adminVerticalSizingTokens($selects);
            $buttonTokens = adminVerticalSizingTokens(adminSubmitButtonClasses($form, ['Filter', 'Apply filters']));

            expect($buttonTokens)->not->toBeEmpty("on {$page}, the submit carries no vertical sizing token to compare");
            expect($buttonTokens)->toEqualCanonicalizing($selectTokens, "on {$page}, the submit's vertical sizing tokens differ from its selects'");

            $hasTapTargetFloor = array_reduce(
                $buttonTokens,
                fn (bool $carry, string $token): bool => $carry || str_starts_with($token, 'min-h-'),
                false,
            );

            expect($hasTapTargetFloor)->toBeTrue("on {$page}, nothing in the row carries a min-h- tap-target floor");
        }
    }
});

it('leaves every existing admin filter test passing — this file only reads markup', function (): void {
    $pages = adminFilterPages($this);

    expect($pages['orders'])->toContain('name="status"')->toContain('name="customer"');
    expect($pages['fulfillments'])->toContain('name="status"')->toContain('name="seller"');
    expect($pages['listings'])->toContain('name="status"')->toContain('name="seller"')->toContain('name="removed"');
    expect($pages['customers'])->toContain('name="standing"');
    expect($pages['ledger'])->toContain('name="seller"')->toContain('name="type"');
    expect($pages['payouts'])->toContain('name="seller"')->toContain('name="as_of"');
    expect($pages['logs'])->toContain('name="event"')->toContain('name="phase"');
});

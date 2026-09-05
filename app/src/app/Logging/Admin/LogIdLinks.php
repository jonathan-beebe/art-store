<?php

declare(strict_types=1);

namespace App\Logging\Admin;

/**
 * Where a prefixed id in the log viewer leads: its detail page where the
 * admin site has one, back into `/admin/logs` as a filter for the two
 * correlation prefixes, and nowhere — rendered plain — for everything else.
 * The route names are drawn from `routes/admin.php`'s own page table, so a
 * link this class produces never 404s.
 */
final class LogIdLinks
{
    /** The prefixes with a detail page, per `routes/admin.php`. A table
     * with no admin page is simply absent here, so its ids render plain. */
    private const array DETAIL_ROUTES = [
        'ord' => 'admin.orders.show',
        'cus' => 'admin.customers.show',
        'sel' => 'admin.sellers.show',
        'lst' => 'admin.listings.show',
        'ful' => 'admin.fulfillments.show',
        'cnv' => 'admin.messages.show',
    ];

    private const string PREFIXED_ID = '/[a-z]{3}_[0-9A-HJKMNP-TV-Z]{26}/';

    /** A row-level id chip shows the prefix plus this many body
     * characters — enough to tell two ids in the same list apart without
     * spending the row's width on a full ULID. */
    private const int TRUNCATED_BODY_LENGTH = 8;

    /** `cus_01J5X3M9A2K8YB7Q4R6T1V0WZE` → `cus_01J5X3M9`, for a collapsed
     * row's id chips (docs/logging.md's expanded panels and the story view
     * show the full id instead). An id with no `_`, or one already this
     * short or shorter, renders as given, not mangled. */
    public static function truncate(string $id): string
    {
        $separator = strpos($id, '_');

        if ($separator === false) {
            return $id;
        }

        $truncatedLength = $separator + 1 + self::TRUNCATED_BODY_LENGTH;

        return strlen($id) > $truncatedLength ? substr($id, 0, $truncatedLength) : $id;
    }

    public static function hrefFor(string $id): ?string
    {
        $prefix = substr($id, 0, 3);

        if ($prefix === 'txn') {
            return route('admin.logs.index', ['txn' => $id]);
        }

        if ($prefix === 'ses') {
            return route('admin.logs.index', ['session' => $id]);
        }

        $routeName = self::DETAIL_ROUTES[$prefix] ?? null;

        return $routeName === null ? null : route($routeName, [$id]);
    }

    /**
     * Text as safe HTML with every linkable prefixed id wrapped in an
     * anchor, for a view to print unescaped. Everything that is not a
     * linked id is HTML-escaped here, the same way `{{ }}` would have.
     */
    public static function linkify(string $text): string
    {
        $matchCount = preg_match_all(self::PREFIXED_ID, $text, $matches, PREG_OFFSET_CAPTURE);

        if ($matchCount === false || $matchCount === 0) {
            return e($text);
        }

        $html = '';
        $consumed = 0;

        foreach ($matches[0] as $match) {
            [$id, $offset] = $match;
            $href = self::hrefFor($id);

            $html .= e(substr($text, $consumed, $offset - $consumed));
            $html .= $href === null ? e($id) : '<a href="'.e($href).'" class="underline">'.e($id).'</a>';
            $consumed = $offset + strlen($id);
        }

        return $html.e(substr($text, $consumed));
    }
}

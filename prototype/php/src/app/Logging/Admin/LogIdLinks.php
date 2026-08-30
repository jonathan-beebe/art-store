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
    /** The prefixes with a detail page, per `routes/admin.php`. A prototype
     * with no admin page for a table (outbox messages) is simply absent
     * here, so its ids render plain. */
    private const array DETAIL_ROUTES = [
        'ord' => 'admin.orders.show',
        'cus' => 'admin.customers.show',
        'sel' => 'admin.sellers.show',
        'lst' => 'admin.listings.show',
        'ful' => 'admin.fulfillments.show',
        'cnv' => 'admin.messages.show',
    ];

    private const string PREFIXED_ID = '/[a-z]{3}_[0-9A-HJKMNP-TV-Z]{26}/';

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

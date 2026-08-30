<?php

declare(strict_types=1);

namespace App\Logging\Admin;

/**
 * Where a log item's own correlation id leads when the id itself is the
 * control: back into `/admin/logs` with that id set as the matching filter
 * (`request`, `txn`, `session`, or `actor`), the caller's other
 * currently-applied filters carried through the same way the pager carries
 * them, and no `page` among them — so the link always lands on page 1.
 */
final class LogFilterLinks
{
    /**
     * @param  'request'|'txn'|'session'|'actor'  $param
     * @param  array<string, string>  $currentFilters  the round-tripped
     *                                                 filter set the page
     *                                                 is already showing,
     *                                                 if any — `page` is
     *                                                 never one of these
     */
    public static function href(string $param, string $id, array $currentFilters = []): string
    {
        unset($currentFilters['page']);

        return route('admin.logs.index', [...$currentFilters, $param => $id]);
    }
}

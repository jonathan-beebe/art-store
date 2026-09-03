---
id: BUG-015
type: bug
status: resolved
created: 2026-09-03
---

# BUG-015: the log viewer scrolls in group mode

## Problem

On `/admin/logs?...&group=1` at `lg` and up, expanding a long request
group — a seeded pay request or a scraper hour, hundreds of lines — leaves
the page unable to scroll to the rest of the group. The admin shell
(`resources/views/components/layouts/admin.blade.php`) makes `body`
`lg:overflow-hidden` and scrolls each pane on its own: `main`'s
`lg:overflow-y-auto` is what is supposed to carry a tall page. `main` is
also `lg:flex lg:flex-col` (DSGN-006), so its direct children — the logs
page's header bar, filter strip, and the grouped-list panel
(`resources/views/admin/logs/index.blade.php`, the
`<div class="overflow-hidden rounded-b-lg ...">` wrapping the `<details
data-group=…>` rows) — are flex items with the default `shrink` behaviour.
With no `shrink-0` of its own, the grouped-list panel is flex-shrunk down
to whatever height fits inside `main`, and because that panel also
carries `overflow-hidden` (for its rounded bottom corners), the part of a
long expanded group past that shrunk height is clipped rather than
reachable — `main` itself never sees an overflow to scroll, since its
child was squeezed to fit before that could happen. Confirmed live: with
`overflow-hidden` on the panel removed, `main.scrollHeight` exceeds
`main.clientHeight`; adding `shrink-0` to the panel instead restores its
natural height (`scrollHeight === clientHeight` on the panel itself)
while keeping the corner clipping, and `main` becomes scrollable.
`resources/views/admin/logs/show.blade.php`'s request-story view wraps
`<x-admin.log-lines>` in the same `overflow-hidden rounded-lg` shape and
carries the identical bug for a request with many lines.

## Goal

A long group reads to its end.

## Outcome

With a group of 300 lines expanded at desktop and phone widths, every
line is reachable by scrolling; the page header and filter bar behave as
they do today. The request-story view (`/admin/logs/requests/{id}`)
scrolls the same way for a request with many lines. A test asserts the
scroll container wraps the grouped list.

## Why it matters

The fraud-lens walk (actor page → Open in logs → expand) ends in this
view.

## Discovery notes

- `resources/views/admin/logs/index.blade.php`'s grouped-list wrapper
  (`overflow-hidden rounded-b-lg border-x border-b ...`, around the
  `@foreach ($groups as $group)` loop) is the clipping element; `main`'s
  `lg:overflow-y-auto` (`resources/views/components/layouts/admin.blade.php`)
  is the intended scroll container and is otherwise unchanged.
- `main` is `lg:flex lg:flex-col` for every `content`/`content-wide` mode
  page, not only logs — the flex-shrink trap applies to any direct child
  of `main` that carries its own vertical `overflow-hidden` and has no
  `shrink-0`. The ungrouped `Lines` view's table wrapper
  (`overflow-x-auto`, horizontal only) does not have this problem and
  needs no change.
- `resources/views/admin/logs/show.blade.php`'s single-request story view
  has the same wrapper shape around `<x-admin.log-lines>` and the same
  bug for a request whose line count is large (up to
  `LogRowQuery::STORY_LINE_CAP` = 1000).
- Reproduced by seeding a 300-line request group through
  `App\Logging\LogStore` directly and loading `/admin/logs?group=1` in a
  real browser at desktop and mobile widths.

## Related work

- DSGN-006 — the admin shell this bug lives inside (nav rail, `main`'s
  own-pane scroll)
- DSGN-004 — log viewer redesign (the grouped `<details>` rows)

## Working

Root cause: `main` (`resources/views/components/layouts/admin.blade.php`)
is `lg:flex lg:flex-col`, so its direct children are flex items with the
default `shrink` behavior. `resources/views/admin/logs/index.blade.php`'s
grouped-list panel and `resources/views/admin/logs/show.blade.php`'s
request-story panel each carry `overflow-hidden` for their rounded
corners and no `shrink-0` of their own, so a long panel gets flex-shrunk
to fit `main`'s height and the part past that shrunk height is clipped
instead of scrolled — `main` never sees the overflow that would make it
scroll.

Fix: `shrink-0` added to both panels' wrapper `<div>`, so each keeps its
natural height and `main`'s `lg:overflow-y-auto` carries it instead.
Verified live (a seeded 300-line request group, real browser, desktop
width): before the fix, removing `overflow-hidden` from the panel made
`main.scrollHeight` exceed `main.clientHeight`; the shipped fix
(`shrink-0` kept alongside `overflow-hidden`) gets the same result —
`main` scrolls, the panel's own `scrollHeight` equals its `clientHeight`
(no content clipped), and the page header, filter bar, and nav stay in
place while the group's last line becomes reachable. Below `lg`, `main`
carries no `lg:flex`/`lg:overflow-y-auto` at all, so the page scrolls in
normal document flow the same as before — unaffected either way.

The ungrouped `Lines` table wrapper (`overflow-x-auto`, horizontal only)
has no vertical `overflow-hidden` to clip and needed no change.

Test: `App\Http\Controllers\Admin\LogControllerTest` —
`it('wraps the grouped request list in a shrink-0 panel so a long group
scrolls inside main instead of being clipped')` builds a 300-line
request group and asserts the panel's class attribute; `it('wraps the
request story\'s line list in the same shrink-0 panel so a long story
scrolls inside main')` does the same for `/admin/logs/requests/{id}`;
`it('leaves the ungrouped Lines table wrapper without shrink-0 — it has
no vertical overflow-hidden to clip')` locks the unchanged wrapper.

`make precommit`: Pint clean, PHPStan `[OK] No errors`, 3947 tests
passed (32714 assertions).

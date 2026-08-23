---
id: BUG-002
type: bug
status: resolved
created: 2026-08-23
---

# BUG-002: Defects the analyzer surfaced

## Problem
Five code paths mishandle a failure value or a wider type than they assume:
- `app/Actions/Listings/StoreListingImage.php:17` stores the result of `Storage::putFile()` as `image_path`; that method returns `false` on a failed write, so a failed upload saves `false` as the image path.
- `app/Domain/Reports/DailyActivity.php:23` calls `->format()` on `DateTimeImmutable::createFromFormat()`, which returns `false` for a malformed date; the field at `:10` is a `'Y-m-d'` string that is parsed back every time.
- `app/Console/Commands/RunWeeklyPayouts.php:19` builds `new DateTimeImmutable($this->option('as-of') ?? 'now')`: `--as-of=yesterdayish` escapes as an uncaught `DateMalformedStringException` with a stack trace, and the `'now'` default reads the system clock so `Carbon::setTestNow()` does not reach it while the web button at `app/Http/Controllers/Seller/PayoutController.php:16` does respect test-now.
- `app/Domain/Payments/FakeCard.php:16-17` passes a possibly-null `preg_replace()` result to `substr()`.
- `app/Actions/Orders/FinalizeOrder.php:48` matches on `OrderStatus` with two arms and no default; it works only because `OrderStatus::fromCardDecision()` happens to return two cases, which the types do not say.
Additionally, the identity cookie's encryption is never pinned: `app/Support/CustomerIdentity.php:30-33` stores a raw integer in `customer_id`, safe only because `EncryptCookies` encrypts it; every test sets it with `withCookie()` (which encrypts), so adding the cookie to the exception list would turn the storefront into "type any integer, read that customer's orders" without a test failing.

## Goal
Each of these paths handles its failure value deliberately, with a test that proves it.

## Outcome
- A failed image write leaves the listing's image path unset and the seller sees an error; it never stores `false`.
- `DailyActivity` carries a date type rather than a string, and no parse-back can fail.
- `php artisan payouts:run --as-of=<not a date>` exits non-zero with a one-line usable message; omitting `--as-of` settles as of the application clock (`now()`), so a test can freeze time and run the command.
- `FakeCard::decide` handles the null case explicitly.
- `FinalizeOrder` branches on a type that only has the outcomes a card decision can produce, with no unreachable arms and no `default`.
- A request carrying an unencrypted `customer_id` cookie is treated as a new anonymous visitor (tests use `withUnencryptedCookie`), and a forged cookie pointing at another customer's id does not resolve to that customer.
- PHPStan reports none of the listed lines.

## Why it matters
These are the "real bugs" column of the static-analysis sweep; fixing them shows the analyzer earning its keep.

## Discovery notes
- A two-case `PaymentOutcome` enum (or narrowing `OrderStatus::fromCardDecision` to a union the analyzer can see) removes the non-exhaustive match.
- For `RunWeeklyPayouts`, Laravel's `$this->option('as-of')` is `string|array|bool|null`; `$this->fail()` / returning `self::FAILURE` with `$this->error()` is the console idiom.
- Clock: one helper for "now as `DateTimeImmutable`" already exists at `ShopController::now()`; the command can use `now()->toDateTimeImmutable()`.

## Related work
- MAINT-001

## Working

Decisions:
- `StoreListingImage::__invoke` returns `?string` (null on a failed disk
  write) instead of `string|false`. This rippled into `UpdateListing`, which
  is not named in this ticket's scope but had a real data-loss bug once the
  return type went nullable: it unconditionally wrote `image_path` from the
  store result and deleted the previous file whenever an image was submitted.
  With a nullable result that meant a failed replacement upload wiped the
  listing's `image_path` to null *and* deleted the still-good previous file.
  Fixed `UpdateListing` to only overwrite/delete when the new write actually
  produced a path, with a new sidecar test forcing the failure via
  `Storage::shouldReceive`. `Seller/ListingController::store()` now appends a
  one-line note to the existing `status` flash when a submitted image failed
  to write (create only — on update, a failed replacement silently keeps the
  old image, and there is no reliable post-hoc signal to distinguish "no
  image submitted" from "replacement failed" without changing action return
  shapes, which was out of scope).
- `DailyActivity::$date` is `DateTimeImmutable`. `ActivityTimeline::day()`
  already builds a `DateTimeImmutable` per day and now passes it straight
  through instead of formatting to a string the constructor immediately
  re-parses. `label()` calls `->format()` directly on the property — no
  parse-back, so no `false` case exists to guard. `show.blade.php` only calls
  `$day->label()`, not `$day->date`, so no view change was needed.
- `RunWeeklyPayouts::handle()` treats a non-empty string `--as-of` as
  user input to parse (wrapped in try/catch for `DateMalformedStringException`,
  printed via `$this->error()`, `self::FAILURE`); anything else (omitted, or
  the option's other possible shapes) falls back to `now()->toDateTimeImmutable()`,
  which respects `Carbon::setTestNow()`/`travelTo()`.
- `FakeCard::decide` guards `preg_replace(...) ?? ''`. Confirmed this is a
  real crash, not just a PHPStan nicety: with `declare(strict_types=1)`
  (used tree-wide), passing the hypothetical `null` to `substr()` throws a
  `TypeError`, not a silent coercion. Proved the branch with a same-namespace
  `preg_replace()` override in the test file (PHP resolves an unqualified
  call from the innermost namespace first), since no real card-number input
  makes the built-in return null.
- `FinalizeOrder`/`OrderStatus::fromCardDecision`: added
  `App\Domain\Payments\PaymentOutcome` (two bare cases, `Approved`/`Declined`,
  with `fromCardDecision(CardDecision): self`). `OrderStatus::fromCardDecision`
  now takes a `PaymentOutcome` and matches it with no default (two arms, two
  cases — statically exhaustive). `FinalizeOrder` computes the outcome once
  and branches its `completePayment`/`releaseStock` match on the outcome
  instead of on `$status` (the post-`transitionTo` value), which is what
  actually removes the `default` arm: `transitionTo(self $next): self` returns
  the full 8-case `OrderStatus` type regardless of the narrower argument
  passed in (no generics on it, and it's BUG-001's file to touch), so matching
  on `$status` could never be exhaustive without a default. Matching on
  `$outcome` (a real two-case type) sidesteps that. Only `fromCardDecision`
  in `OrderStatus.php` was touched, per the concurrent-edit boundary with
  BUG-001's `transitionTo` work.
- `ResolveCustomerIdentityTest`: added two tests using
  `withUnencryptedCookie()` against real customer ids. `bootstrap/app.php`
  was not touched (BUG-001's file) and does not currently except `customer_id`
  from `EncryptCookies`, so an unencrypted cookie fails decryption and reaches
  the middleware as an absent cookie today — these tests pin that so a future
  regression (adding `customer_id` to the encrypt-except list) fails a test
  instead of silently becoming "type any integer, read that customer's
  orders."

Numbers:
- Pest: 510 passed (1185 assertions) after; suite was run concurrently with
  BUG-001's in-flight edits to shared files, so there is no clean isolated
  "before" count for this ticket alone. Added 11 new test cases across the
  ticket's scope (`StoreListingImageTest` +1, `UpdateListingTest` +1,
  `ListingControllerTest` +2, `PaymentOutcomeTest` +2 new file,
  `RunWeeklyPayoutsTest` +2, `FakeCardTest` +1, `ResolveCustomerIdentityTest`
  +2); `OrderStatusTest`'s two `fromCardDecision` tests were renamed/rewritten
  in place, not added.
- PHPStan: 47 errors before this ticket's changes, 44 after. All five lines
  named in the ticket (`StoreListingImage.php:19`, `DailyActivity.php:25`,
  `FakeCard.php:19`, plus the two `FinalizeOrder`/`OrderStatus` match sites,
  which PHPStan was not actually flagging but the ticket's Goal called for
  exhaustiveness anyway) are clear. The remaining 44 errors are all in files
  this ticket does not touch (nullable `auth()->user()`, `mixed` from
  `$request->validated()`, and one pre-existing `OrderStatus::fromFulfillments`
  "always false" comparison unrelated to `fromCardDecision`).
- Pint: clean (one `phpdoc_align` issue in `StoreListingImage.php`, fixed).

Outcome bullets:
- A failed image write leaves the listing's image path unset and the seller
  sees an error; it never stores `false` — done (create path shows the error;
  update path keeps the old image silently, see decisions above).
- `DailyActivity` carries a date type rather than a string, no parse-back can
  fail — done.
- `payouts:run --as-of=<garbage>` exits non-zero with a one-line message;
  omitted `--as-of` uses `now()` and is testable with frozen time — done.
- `FakeCard::decide` handles the null case explicitly — done.
- `FinalizeOrder` branches on a type with only the outcomes a card decision
  can produce, no unreachable arms, no default — done.
- Unencrypted/forged `customer_id` cookie does not resolve to another
  customer — done (pinned via `withUnencryptedCookie` tests;
  `bootstrap/app.php` untouched, per the concurrent-edit boundary).
- PHPStan reports none of the listed lines — done.

Left out / deviations:
- Touched `app/Actions/Listings/UpdateListing.php` (+ its test) though the
  ticket named only `StoreListingImage.php` and the controller — required to
  avoid a data-loss regression the type change introduced (see decisions).
- Did not add an update-path error flash (see decisions) — no reliable signal
  without changing `UpdateListing`'s return contract, which felt like scope
  creep beyond this ticket's "keep the edit minimal" instruction.

---
id: BUG-002
type: bug
status: open
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

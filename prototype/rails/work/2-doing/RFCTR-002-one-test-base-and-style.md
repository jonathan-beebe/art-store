---
id: RFCTR-002
type: refactor
status: open
created: 2026-08-22
---

# RFCTR-002: One test base, one declaration style

## Problem
`src/test/commerce_test_case.rb`, `identity_test_case.rb`, `seller_portal_test_case.rb` and `shop_test_case.rb` define four base classes and two modules that re-declare the same record builders with small differences: the three card-number constants appear three times, `shipping_address` and `purchaser` twice, `create_listing` in three variants, `moment`/`unique_email`/`unique_slug` twice. Test files mix `def test_foo` with `test "foo" do`.

## Goal
A contributor adds a test by subclassing the stock Rails base and reaching for one obvious builder.

## Outcome
`test/test_helper.rb` is the only base setup; every test subclasses `ActiveSupport::TestCase`, `ActionDispatch::IntegrationTest` or `ActionView::TestCase` directly; each record builder and sign-in helper is defined once; every test uses the `test "..." do` style; the run count is unchanged.

## Why it matters
Duplicated builders drift (they already differ on which listing fields are filled), and a newcomer has to learn four base classes before writing a test.

## Discovery notes
`test/support/*.rb` required from `test_helper.rb` is the common Rails shape. Several identity tests assert `Customer.count`, so default emails must stay unique per call. The short builder names (`seller`, `customer`, `listing(...)`) are used hundreds of times; keeping them as aliases is acceptable if renaming call sites proves risky.

## Related work
- RFCTR-001

## Working

The declarative style landed first, on its own. 390 `def test_<name>` definitions across 73 files became `test "<name>" do`, and the two remaining `def setup` methods became `setup do`. The wording comes straight from the method name with underscores turned into spaces, so no test changed what it asserts. The suite stayed at 645 runs, 1604 assertions, 0 failures, and line coverage stayed at 100%.

require "test_helper"

class LedgerEntryTest < ActiveSupport::TestCase
  test "a hold adds the seller net to escrow" do
    entry = LedgerEntry.hold(fulfillment, at: moment("2026-08-20 10:00:00"))

    assert_predicate entry, :held?
    assert_equal 40_500, entry.amount_cents
    assert_equal fulfillment.seller_id, entry.seller_id
    assert_equal moment("2026-08-20 10:00:00"), entry.occurred_at
  end

  test "a release adds the seller net to what is available" do
    entry = LedgerEntry.release(fulfillment, at: moment("2026-08-22 09:00:00"))

    assert_predicate entry, :released?
    assert_equal 40_500, entry.amount_cents
    assert_equal fulfillment.id, entry.fulfillment_id
  end

  test "a payout leaves the ledger, so its entry is negative" do
    entry = LedgerEntry.pay_out(payout, at: moment("2026-08-23 23:59:59"))

    assert_predicate entry, :paid_out?
    assert_equal(-40_500, entry.amount_cents)
    assert_equal payout.id, entry.payout_id
    assert_equal payout.seller_id, entry.seller_id
  end

  test "an empty ledger owes nothing" do
    balance = LedgerEntry.balance

    assert_equal 0, balance.held.cents
    assert_equal 0, balance.available.cents
    assert_equal 0, balance.paid_out.cents
    refute_predicate balance, :payable?
  end

  test "a hold waits on delivery" do
    LedgerEntry.hold(fulfillment, at: moment("2026-08-20 10:00:00"))

    balance = LedgerEntry.balance
    assert_equal 40_500, balance.held.cents
    assert_equal 0, balance.available.cents
    refute_predicate balance, :payable?
  end

  test "a release moves the hold to available" do
    hold_and_release

    balance = LedgerEntry.balance
    assert_equal 0, balance.held.cents
    assert_equal 40_500, balance.available.cents
    assert_predicate balance, :payable?
  end

  test "a payout empties what was available" do
    hold_and_release
    LedgerEntry.pay_out(payout, at: moment("2026-08-23 23:59:59"))

    balance = LedgerEntry.balance
    assert_equal 0, balance.available.cents
    assert_equal 40_500, balance.paid_out.cents
    refute_predicate balance, :payable?
  end

  test "it folds a ledger that holds and releases more than once" do
    hold_and_release
    LedgerEntry.hold(second_fulfillment, at: moment("2026-08-23 10:00:00"))

    balance = LedgerEntry.balance
    assert_equal 9000, balance.held.cents
    assert_equal 40_500, balance.available.cents
  end

  test "occurred_by leaves out what happened after the moment" do
    LedgerEntry.hold(fulfillment, at: moment("2026-08-20 10:00:00"))
    LedgerEntry.hold(second_fulfillment, at: moment("2026-08-23 10:00:00"))

    assert_equal 40_500, LedgerEntry.occurred_by(moment("2026-08-21 00:00:00")).balance.held.cents
  end

  test "it folds each seller's ledger on its own, in seller id order" do
    LedgerEntry.hold(fulfillment, at: moment("2026-08-20 10:00:00"))
    LedgerEntry.hold(second_fulfillment, at: moment("2026-08-20 10:00:00"))

    balances = LedgerEntry.balances_by_seller

    assert_equal [fulfillment.seller_id, second_fulfillment.seller_id].sort, balances.keys
    assert_equal 40_500, balances.fetch(fulfillment.seller_id).held.cents
    assert_equal 9000, balances.fetch(second_fulfillment.seller_id).held.cents
  end

  private

  def hold_and_release
    LedgerEntry.hold(fulfillment, at: moment("2026-08-20 10:00:00"))
    LedgerEntry.release(fulfillment, at: moment("2026-08-22 09:00:00"))
  end

  def fulfillment
    @fulfillment ||= sale_of(45_000)
  end

  def second_fulfillment
    @second_fulfillment ||= sale_of(10_000)
  end

  def payout
    @payout ||= Payout.create!(
      seller_id: fulfillment.seller_id, period_start: Date.new(2026, 8, 17), period_end: Date.new(2026, 8, 23),
      amount_cents: 40_500, paid_at: moment("2026-08-24 09:00:00")
    )
  end

  # An order placed but not paid, so the fulfillment carries a net with no
  # ledger row behind it yet.
  def sale_of(price_cents)
    order = order_for(create_verified_customer, create_listing(create_seller, price_cents: price_cents))
    order.fulfillments.sole
  end
end

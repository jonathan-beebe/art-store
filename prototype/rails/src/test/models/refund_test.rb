require "test_helper"

class RefundTest < ActiveSupport::TestCase
  test "issuing a refund records who sent the money back, and why" do
    seller = fulfillment.seller
    refund = Refund.issue(fulfillment, reason: "The kiln cracked it.", by: seller, at: moment("2026-08-21 09:00:00"))

    assert_equal fulfillment.order_id, refund.order_id
    assert_equal fulfillment.id, refund.fulfillment_id
    assert_equal fulfillment.order.approved_payment.id, refund.payment_id
    assert_equal fulfillment.subtotal_cents, refund.amount_cents
    assert_equal "The kiln cracked it.", refund.reason
    assert_equal "seller", refund.issued_by_type
    assert_equal seller.id, refund.issued_by_id
    assert_equal moment("2026-08-21 09:00:00"), refund.created_at
  end

  test "an admin refund says the platform issued it" do
    admin = create_admin
    refund = Refund.issue(fulfillment, reason: "Dispute found for the buyer.", by: admin, at: Time.current)

    assert_predicate refund, :issued_by_admin?
    assert_equal admin.id, refund.issued_by_id
  end

  test "a refund adds to the order's running total" do
    Refund.issue(fulfillment, reason: "Out of stock.", by: fulfillment.seller, at: Time.current)

    assert_equal fulfillment.subtotal_cents, fulfillment.order.reload.refunded_cents
    assert_equal fulfillment.subtotal.format, fulfillment.order.reload.refunded.format
  end

  test "a refund writes the ledger entry that takes the net off the seller" do
    Refund.issue(fulfillment, reason: "Out of stock.", by: fulfillment.seller, at: moment("2026-08-21 09:00:00"))

    entry = LedgerEntry.refunded.sole
    assert_equal(-fulfillment.net_cents, entry.amount_cents)
    assert_equal fulfillment.id, entry.fulfillment_id
  end

  test "a refund needs a reason" do
    error = assert_raises(ActiveRecord::RecordInvalid) do
      Refund.issue(fulfillment, reason: "   ", by: fulfillment.seller, at: Time.current)
    end

    assert_includes error.record.errors.full_messages, Refund::MISSING_REASON
    assert_equal 0, Refund.count
  end

  test "a reason has an upper bound" do
    error = assert_raises(ActiveRecord::RecordInvalid) do
      Refund.issue(fulfillment, reason: "x" * (Refund::REASON_LIMIT + 1), by: fulfillment.seller, at: Time.current)
    end

    assert_includes error.record.errors.full_messages, Refund::LONG_REASON
  end

  test "a reason at the bound is kept" do
    refund = Refund.issue(
      fulfillment, reason: "x" * Refund::REASON_LIMIT, by: fulfillment.seller, at: Time.current
    )

    assert_equal Refund::REASON_LIMIT, refund.reason.length
  end

  test "the database refuses a second refund row for the same fulfillment" do
    Refund.issue(fulfillment, reason: "First issue.", by: fulfillment.seller, at: Time.current)

    # Calling .issue directly a second time, rather than going through
    # Fulfillment#decline!/#refund!, bypasses the transition guard that
    # already refuses a second decline or refund — this is the unique index
    # on refunds.fulfillment_id catching it on its own.
    assert_raises(ActiveRecord::RecordNotUnique) do
      Refund.issue(fulfillment, reason: "Second issue.", by: fulfillment.seller, at: Time.current)
    end
  end

  private

  def fulfillment
    @fulfillment ||= paid_order_for(create_verified_customer, create_listing).fulfillments.sole
  end
end

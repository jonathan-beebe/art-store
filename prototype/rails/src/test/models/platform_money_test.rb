require "test_helper"

class PlatformMoneyTest < ActiveSupport::TestCase
  test "an empty platform owes and earns nothing" do
    money = PlatformMoney.fold

    assert_equal 0, money.held.cents
    assert_equal 0, money.available.cents
    assert_equal 0, money.paid_out.cents
    assert_equal 0, money.fees_earned.cents
    assert_equal 0, money.fees_refunded.cents
    assert_equal 0, money.refunded.cents
  end

  test "a delivered sale releases its net and earns its fee" do
    fulfillment = delivered_fulfillment(price_cents: 45_000)

    money = PlatformMoney.fold

    assert_equal 0, money.held.cents
    assert_equal fulfillment.net_cents, money.available.cents
    assert_equal fulfillment.fee_cents, money.fees_earned.cents
    assert_equal 0, money.fees_refunded.cents
  end

  test "a declined sale forgoes its fee and folds back to zero" do
    fulfillment = declined_fulfillment(price_cents: 45_000)

    money = PlatformMoney.fold

    assert_equal 0, money.held.cents
    assert_equal 0, money.available.cents
    assert_equal 0, money.fees_earned.cents
    assert_equal fulfillment.fee_cents, money.fees_refunded.cents
    assert_equal fulfillment.subtotal_cents, money.refunded.cents
  end

  test "fees earned and fees refunded read apart across two sellers" do
    earned = delivered_fulfillment(price_cents: 45_000)
    refunded = declined_fulfillment(price_cents: 20_000)

    money = PlatformMoney.fold

    assert_equal earned.fee_cents, money.fees_earned.cents
    assert_equal refunded.fee_cents, money.fees_refunded.cents
  end

  private

  def delivered_fulfillment(price_cents:)
    seller = create_seller
    order = paid_order_for(create_verified_customer, create_listing(seller, price_cents: price_cents))
    fulfillment = order.fulfillments.sole
    fulfillment.ship!(carrier: "Royal Mail", tracking_number: "RM1", at: moment("2026-08-21 09:00:00"))
    fulfillment.deliver!(at: moment("2026-08-22 09:00:00"))
  end

  def declined_fulfillment(price_cents:)
    seller = create_seller
    order = paid_order_for(create_verified_customer, create_listing(seller, price_cents: price_cents))
    fulfillment = order.fulfillments.sole
    fulfillment.decline!(reason: "Out of stock", by: seller, at: moment("2026-08-21 09:00:00"))
    fulfillment
  end
end

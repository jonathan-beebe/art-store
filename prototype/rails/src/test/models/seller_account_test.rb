require "test_helper"

class SellerAccountTest < ActiveSupport::TestCase
  include IntegrationHelpers

  test "a seller with no ledger reads a zero account" do
    seller = create_seller

    account = SellerAccount.for_every_seller.find { |row| row.id == seller.id }

    assert_equal 0, account.held.cents
    assert_equal 0, account.available.cents
    assert_equal 0, account.paid_out.cents
    assert_equal 0, account.fees_earned.cents
    assert_equal 0, account.fees_refunded.cents
    assert_equal 0, account.refunded.cents
  end

  test "every seller appears, whether or not they moved any money" do
    silent = create_seller
    create_seller

    ids = SellerAccount.for_every_seller.map(&:id)

    assert_includes ids, silent.id
    assert_equal SellerAccount.for_every_seller.size, Seller.count
  end

  test "each seller's figures fold from their own fulfillments only" do
    first_seller = create_seller
    second_seller = create_seller
    first_order = paid_order_for(create_verified_customer, create_listing(first_seller, price_cents: 45_000))
    second_order = paid_order_for(create_verified_customer, create_listing(second_seller, price_cents: 20_000))
    first_fulfillment = first_order.fulfillments.sole
    first_fulfillment.ship!(carrier: "Royal Mail", tracking_number: "RM1", at: moment("2026-08-21 09:00:00"))
    first_fulfillment.deliver!(at: moment("2026-08-22 09:00:00"))
    second_fulfillment = second_order.fulfillments.sole
    second_fulfillment.decline!(reason: "Out of stock", by: second_seller, at: moment("2026-08-21 09:00:00"))

    accounts = SellerAccount.for_every_seller.index_by(&:id)

    assert_equal first_fulfillment.net_cents, accounts[first_seller.id].available.cents
    assert_equal first_fulfillment.fee_cents, accounts[first_seller.id].fees_earned.cents
    assert_equal 0, accounts[first_seller.id].fees_refunded.cents

    assert_equal 0, accounts[second_seller.id].available.cents
    assert_equal 0, accounts[second_seller.id].fees_earned.cents
    assert_equal second_fulfillment.fee_cents, accounts[second_seller.id].fees_refunded.cents
    assert_equal second_fulfillment.subtotal_cents, accounts[second_seller.id].refunded.cents
  end

  test "the fold costs the same statements however many sellers hold money" do
    build_delivered_seller
    one = count_queries { SellerAccount.for_every_seller }

    4.times { build_delivered_seller }
    five = count_queries { SellerAccount.for_every_seller }

    assert_equal one, five
  end

  private

  def build_delivered_seller
    seller = create_seller
    order = paid_order_for(create_verified_customer, create_listing(seller, price_cents: 45_000))
    fulfillment = order.fulfillments.sole
    fulfillment.ship!(carrier: "Royal Mail", tracking_number: "RM1", at: moment("2026-08-21 09:00:00"))
    fulfillment.deliver!(at: moment("2026-08-22 09:00:00"))
  end
end

require "test_helper"

class SeedsTest < ActiveSupport::TestCase
  setup { Rails.application.load_seed }

  test "it seeds four verified sellers" do
    assert_equal 4, Seller.count
    assert_equal 4, Seller.where.not(email_verified_at: nil).count
  end

  test "it seeds listings across statuses and media" do
    for_sale = Listing.where(status: "for_sale")

    assert_equal 24, for_sale.count
    assert_equal 3, Listing.where(status: "draft").count
    assert_equal 2, Listing.where(status: "sold").count
    assert_equal %w[ceramic painting photography print sculpture textile], for_sale.distinct.order(:medium).pluck(:medium)
  end

  test "it seeds one verified customer with favorites and view history" do
    customer = Customer.find_by(email: "casey@example.com")

    assert customer.present?
    assert customer.email_verified_at.present?
    assert_equal 3, Favorite.where(customer_id: customer.id).count
    assert_equal 12, ListingEvent.count
  end

  test "it seeds order history for two sellers" do
    assert_equal 3, Order.count
    assert_equal 0, Order.where(status: Domain::Orders::OrderStatus::PENDING_VERIFICATION).count

    assert_equal 1, Fulfillment.where(status: Domain::Orders::FulfillmentStatus::AWAITING_SHIPMENT).count
    assert_equal 1, Fulfillment.where(status: Domain::Orders::FulfillmentStatus::SHIPPED).count
    assert_equal 1, Fulfillment.where(status: Domain::Orders::FulfillmentStatus::DELIVERED).count
    assert_equal 2, Fulfillment.distinct.count(:seller_id)

    assert_equal 3, Payment.count
  end

  test "it releases and pays out the delivered order" do
    assert_equal 3, LedgerEntry.where(entry_type: Domain::Escrow::LedgerEntryType::HELD).count
    assert_equal 1, LedgerEntry.where(entry_type: Domain::Escrow::LedgerEntryType::RELEASED).count
    assert_equal 1, LedgerEntry.where(entry_type: Domain::Escrow::LedgerEntryType::PAID_OUT).count

    payout = Payout.sole
    delivered_fulfillment = Fulfillment.find_by!(status: Domain::Orders::FulfillmentStatus::DELIVERED)

    assert_equal delivered_fulfillment.seller_id, payout.seller_id
    assert_equal delivered_fulfillment.net_cents, payout.amount_cents
  end

  test "it notifies sellers and the customer" do
    assert_equal 5, Notification.count
  end

  test "it does not duplicate data on a second run" do
    Rails.application.load_seed

    assert_equal 4, Seller.count
  end
end

require "test_helper"

module Shop
  class DeliveryConfirmationsControllerTest < ActionDispatch::IntegrationTest
    test "the customer confirms delivery and the escrow is released" do
      order = shipped_order
      fulfillment = order.fulfillments.sole

      post shop_confirm_delivery_path(order_id: order.id, id: fulfillment.id)

      assert_redirected_to shop_order_path(order)
      assert_equal Domain::Orders::FulfillmentStatus::DELIVERED, fulfillment.reload.status
      assert_equal "delivered", order.reload.status
      assert_includes fulfillment.ledger_entries.pluck(:entry_type), Domain::Escrow::LedgerEntryType::RELEASED
    end

    test "a fulfillment that has not shipped cannot be confirmed" do
      order = paid_order

      post shop_confirm_delivery_path(order_id: order.id, id: order.fulfillments.sole.id)

      assert_response :not_found
      assert_equal Domain::Orders::FulfillmentStatus::AWAITING_SHIPMENT, order.fulfillments.sole.reload.status
    end

    test "another customer cannot confirm delivery" do
      order = shipped_order
      fulfillment = order.fulfillments.sole
      end_session
      sign_in_as_customer(email: "stranger@example.com")

      post shop_confirm_delivery_path(order_id: order.id, id: fulfillment.id)

      assert_response :not_found
      assert_equal Domain::Orders::FulfillmentStatus::SHIPPED, fulfillment.reload.status
    end

    test "a fulfillment of another order cannot be confirmed through this one" do
      order = shipped_order
      other = paid_order

      post shop_confirm_delivery_path(order_id: other.id, id: order.fulfillments.sole.id)

      assert_response :not_found
    end

    private

    def paid_order
      sign_in_as_customer(email: "buyer@example.com")
      post shop_add_to_cart_path(slug: create_listing.slug)
      post shop_place_order_path,
        params: { email: "buyer@example.com", card_number: APPROVED_CARD }.merge(shipping_params)

      visiting_customer.orders.order(:id).last
    end

    def shipped_order
      paid_order.tap do |order|
        Fulfillments::MarkShipped.new.call(
          fulfillment: order.fulfillments.sole, carrier: "Royal Mail", tracking_number: "RM123",
          now: Time.zone.parse("2026-08-21 09:00:00")
        )
      end
    end
  end
end

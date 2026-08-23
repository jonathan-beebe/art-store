require "test_helper"

class Seller::ShipmentsControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor ships nothing" do
    fulfillment = create_fulfillment(other_seller)

    post seller_order_shipment_path(fulfillment), params: shipment

    assert_redirected_to seller_login_path
    assert_equal Domain::Orders::FulfillmentStatus::AWAITING_SHIPMENT, fulfillment.reload.status
  end

  test "marking shipped records the carrier and the tracking number" do
    seller = signed_in_seller
    fulfillment = create_fulfillment(seller)

    post seller_order_shipment_path(fulfillment), params: shipment

    assert_redirected_to seller_order_path(fulfillment)
    fulfillment.reload
    assert_equal Domain::Orders::FulfillmentStatus::SHIPPED, fulfillment.status
    assert_equal "Royal Mail", fulfillment.carrier
    assert_equal "RM123456789GB", fulfillment.tracking_number
    assert_not_nil fulfillment.shipped_at
    follow_redirect!
    assert_select "[data-flash=notice]", text: "Marked shipped."
  end

  test "shipping the only fulfillment ships the order and tells the customer" do
    seller = signed_in_seller
    fulfillment = create_fulfillment(seller)

    post seller_order_shipment_path(fulfillment), params: shipment

    assert_equal Domain::Orders::OrderStatus::SHIPPED, fulfillment.order.reload.status
    assert_equal "Order shipped", Notification.where(customer_id: fulfillment.order.customer_id).last.subject
  end

  test "a shipment with no carrier is refused" do
    seller = signed_in_seller
    fulfillment = create_fulfillment(seller)

    post seller_order_shipment_path(fulfillment), params: shipment(carrier: " ")

    assert_response :unprocessable_content
    assert_select "[data-refusal]", text: /carrier and a tracking number/
    assert_equal Domain::Orders::FulfillmentStatus::AWAITING_SHIPMENT, fulfillment.reload.status
  end

  test "shipping an order that already shipped is refused" do
    seller = signed_in_seller
    fulfillment = create_fulfillment(seller)
    post seller_order_shipment_path(fulfillment), params: shipment

    post seller_order_shipment_path(fulfillment), params: shipment(tracking_number: "RM987654321GB")

    assert_response :unprocessable_content
    assert_select "[data-refusal]", text: "A fulfillment cannot move from shipped to shipped."
    assert_equal "RM123456789GB", fulfillment.reload.tracking_number
  end

  test "shipping another seller's order is not found" do
    signed_in_seller
    rival = create_fulfillment(other_seller)

    post seller_order_shipment_path(rival), params: shipment

    assert_response :not_found
    assert_equal Domain::Orders::FulfillmentStatus::AWAITING_SHIPMENT, rival.reload.status
  end

  private

  def shipment(**overrides)
    { carrier: "Royal Mail", tracking_number: "RM123456789GB" }.merge(overrides)
  end
end

require "test_helper"

class NotificationTest < ActiveSupport::TestCase
  test "a sale files under the seller and says what is held" do
    shop = create_seller
    fulfillment = fulfillment_for(shop)

    notification = Notification.item_sold(fulfillment)

    assert_equal shop, notification.recipient
    assert_equal "Item sold", notification.subject
    assert_equal(
      "Order ##{fulfillment.order_id} is paid. $405.00 is held until the customer confirms delivery.",
      notification.body
    )
  end

  test "a shipment files under the customer and says how to track it" do
    buyer = create_verified_customer
    fulfillment = fulfillment_for(create_seller, buyer: buyer)
    fulfillment.update!(carrier: "USPS", tracking_number: "9400111899")

    notification = Notification.order_shipped(fulfillment)

    assert_equal buyer, notification.recipient
    assert_equal "Order shipped", notification.subject
    assert_equal(
      "Order ##{fulfillment.order_id} shipped with USPS. Tracking number 9400111899.",
      notification.body
    )
  end

  test "a new notification is unread" do
    notification = Notification.item_sold(fulfillment_for(create_seller))

    assert_includes Notification.unread, notification
  end

  test "a notification carries the page it is about" do
    notification = Notification.item_sold(fulfillment_for(create_seller))
    notification.update!(url: "/seller/orders/7")

    assert_equal "/seller/orders/7", notification.reload.url
  end

  test "reading one stamps the moment it was read" do
    notification = Notification.item_sold(fulfillment_for(create_seller))

    notification.read!(at: moment("2026-08-22 09:00:00"))

    assert_equal moment("2026-08-22 09:00:00"), notification.reload.read_at
    assert_empty Notification.unread
  end

  private

  def fulfillment_for(seller, buyer: create_verified_customer)
    order_for(buyer, create_listing(seller)).fulfillments.sole
  end
end

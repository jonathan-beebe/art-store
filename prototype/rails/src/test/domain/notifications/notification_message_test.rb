require "test_helper"

module Domain
  module Notifications
    class NotificationMessageTest < ActiveSupport::TestCase
      test "a sale tells the seller what is held and why" do
        message = NotificationMessage.item_sold(7, Money.from_cents(40_500))

        assert_equal "Item sold", message.subject
        assert_equal "Order #7 is paid. $405.00 is held until the customer confirms delivery.", message.body
        assert_nil message.url
      end

      test "a shipment tells the customer how to track it" do
        message = NotificationMessage.order_shipped(7, "USPS", "9400111899")

        assert_equal "Order shipped", message.subject
        assert_equal "Order #7 shipped with USPS. Tracking number 9400111899.", message.body
      end

      test "a message takes a url to the page it is about" do
        message = NotificationMessage.item_sold(7, Money.from_cents(40_500)).with(url: "/seller/orders/7")

        assert_equal "/seller/orders/7", message.url
        assert_equal "Item sold", message.subject
      end
    end
  end
end

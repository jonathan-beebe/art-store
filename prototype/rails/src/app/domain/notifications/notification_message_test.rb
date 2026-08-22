# Runs without Rails: ruby -Iapp app/domain/notifications/notification_message_test.rb
require "minitest/autorun"
require_relative "notification_message"

module Domain
  module Notifications
    class NotificationMessageTest < Minitest::Test
      def test_a_sale_tells_the_seller_what_is_held_and_why
        message = NotificationMessage.item_sold(7, Money.from_cents(40_500))

        assert_equal "Item sold", message.subject
        assert_equal "Order #7 is paid. $405.00 is held until the customer confirms delivery.", message.body
        assert_nil message.url
      end

      def test_a_shipment_tells_the_customer_how_to_track_it
        message = NotificationMessage.order_shipped(7, "USPS", "9400111899")

        assert_equal "Order shipped", message.subject
        assert_equal "Order #7 shipped with USPS. Tracking number 9400111899.", message.body
      end

      def test_a_message_takes_a_url_to_the_page_it_is_about
        message = NotificationMessage.item_sold(7, Money.from_cents(40_500)).with(url: "/seller/orders/7")

        assert_equal "/seller/orders/7", message.url
        assert_equal "Item sold", message.subject
      end
    end
  end
end

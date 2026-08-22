require "commerce_test_case"

module Notifications
  class NotifyTest < CommerceTestCase
    test "it files a seller message under the seller" do
      shop = seller

      notification = Notify.new.call(
        recipient_type: Domain::Notifications::RecipientType::SELLER,
        recipient_id: shop.id,
        message: Domain::Notifications::NotificationMessage.item_sold(7, Domain::Money.from_cents(40_500))
      )

      assert_equal shop.id, notification.seller_id
      assert_nil notification.customer_id
      assert_equal "Item sold", notification.subject
    end

    test "it files a customer message under the customer" do
      buyer = customer

      notification = Notify.new.call(
        recipient_type: Domain::Notifications::RecipientType::CUSTOMER,
        recipient_id: buyer.id,
        message: Domain::Notifications::NotificationMessage.order_shipped(7, "USPS", "9400111899")
      )

      assert_equal buyer.id, notification.customer_id
      assert_nil notification.seller_id
    end

    test "a new notification is unread" do
      notification = Notify.new.call(
        recipient_type: Domain::Notifications::RecipientType::SELLER,
        recipient_id: seller.id,
        message: Domain::Notifications::NotificationMessage.item_sold(7, Domain::Money.from_cents(40_500))
      )

      assert_includes Notification.unread, notification
    end

    test "it carries the url the message points at" do
      message = Domain::Notifications::NotificationMessage
                  .item_sold(7, Domain::Money.from_cents(40_500))
                  .with(url: "/seller/orders/7")

      notification = Notify.new.call(
        recipient_type: Domain::Notifications::RecipientType::SELLER,
        recipient_id: seller.id,
        message: message
      )

      assert_equal "/seller/orders/7", notification.url
    end
  end
end

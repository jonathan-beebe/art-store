require "commerce_test_case"

module Notifications
  class NotifyTest < CommerceTestCase
    def test_it_files_a_seller_message_under_the_seller
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

    def test_it_files_a_customer_message_under_the_customer
      buyer = customer

      notification = Notify.new.call(
        recipient_type: Domain::Notifications::RecipientType::CUSTOMER,
        recipient_id: buyer.id,
        message: Domain::Notifications::NotificationMessage.order_shipped(7, "USPS", "9400111899")
      )

      assert_equal buyer.id, notification.customer_id
      assert_nil notification.seller_id
    end

    def test_a_new_notification_is_unread
      notification = Notify.new.call(
        recipient_type: Domain::Notifications::RecipientType::SELLER,
        recipient_id: seller.id,
        message: Domain::Notifications::NotificationMessage.item_sold(7, Domain::Money.from_cents(40_500))
      )

      assert_includes Notification.unread, notification
    end

    def test_it_carries_the_url_the_message_points_at
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

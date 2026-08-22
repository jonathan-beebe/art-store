require "seller_portal_test_case"

class Seller::NotificationReadsControllerTest < SellerPortalTestCase
  test "a signed-out visitor marks nothing read" do
    notification = create_notification(other_seller)

    post seller_notification_read_path(notification)

    assert_redirected_to seller_login_path
    assert_nil notification.reload.read_at
  end

  test "marking a notification read clears it from the unread count" do
    seller = signed_in_seller
    notification = create_notification(seller)

    post seller_notification_read_path(notification)

    assert_redirected_to seller_notifications_path
    assert_not_nil notification.reload.read_at
    assert_empty seller.notifications.unread
    follow_redirect!
    assert_select "[data-flash=notice]", text: "Marked as read."
  end

  test "marking another seller's notification read is not found" do
    signed_in_seller
    rival = create_notification(other_seller)

    post seller_notification_read_path(rival)

    assert_response :not_found
    assert_nil rival.reload.read_at
  end
end

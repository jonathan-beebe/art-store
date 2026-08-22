require "seller_portal_test_case"

class Seller::NotificationsControllerTest < SellerPortalTestCase
  test "a signed-out visitor reads no notifications" do
    get seller_notifications_path

    assert_redirected_to seller_login_path
  end

  test "it lists the seller's notifications newest first" do
    seller = signed_in_seller
    create_notification(seller, subject: "Older")
    create_notification(seller, subject: "Newer")

    get seller_notifications_path

    assert_response :success
    assert_select "[data-notification]", 2
    assert_select "[data-notification]:first-of-type", text: /Newer/
  end

  test "an unread notification is badged and offers the mark-read form" do
    seller = signed_in_seller
    unread = create_notification(seller)
    read = create_notification(seller, subject: "Seen already", read_at: Time.current)

    get seller_notifications_path

    assert_select "[data-notification=?]", unread.id.to_s do
      assert_select "[data-unread]", text: "Unread"
      assert_select "form[action=?][method=post]", seller_notification_read_path(unread)
    end
    assert_select "[data-notification=?]", read.id.to_s do
      assert_select "[data-unread]", false
      assert_select "form", false
    end
  end

  test "another seller's notifications stay off the page" do
    signed_in_seller
    create_notification(other_seller, subject: "Rival notice")

    get seller_notifications_path

    assert_select "[data-notification]", false
    assert_select "p", text: "Nothing yet."
  end
end

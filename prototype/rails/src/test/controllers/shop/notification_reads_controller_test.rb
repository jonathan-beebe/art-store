require "test_helper"

module Shop
  class NotificationReadsControllerTest < ActionDispatch::IntegrationTest
    test "it marks a notification read" do
      sign_in_as_customer(email: "buyer@example.com")
      notification = notify(visiting_customer, "Order shipped")

      post shop_read_notification_path(id: notification.id)

      assert_redirected_to shop_account_path
      refute_nil notification.reload.read_at
      follow_redirect!
      assert_select "button", text: "Mark as read", count: 0
    end

    test "it leaves another customer's notification alone" do
      stranger = create_verified_customer(email: "stranger@example.com")
      notification = notify(stranger, "Order shipped")
      sign_in_as_customer(email: "buyer@example.com")

      post shop_read_notification_path(id: notification.id)

      assert_response :not_found
      assert_nil notification.reload.read_at
    end

    private

    def notify(customer, subject)
      Notification.create!(recipient: customer, subject: subject, body: "Tracking RM123.")
    end
  end
end

require "test_helper"

module Shop
  class AccountControllerTest < ActionDispatch::IntegrationTest
    test "it shows the verified address and a sign-out form" do
      sign_in_as_customer(email: "buyer@example.com")

      get shop_account_path

      assert_response :success
      assert_select "[data-account-email]", "buyer@example.com"
      assert_select "form[action=?][method=?]", customer_logout_path, "post"
    end

    test "a visitor who has not verified an address is sent to sign in" do
      get shop_account_path

      assert_redirected_to customer_login_path(redirect_to: shop_account_path)
    end

    test "an identity carried only by the cookie is not a sign-in" do
      customer = create_verified_customer
      get root_path
      cookies[CustomerIdentity::COOKIE.to_s] = signed_cookie_for(customer.id)

      get shop_account_path

      assert_redirected_to customer_login_path(redirect_to: shop_account_path)
    end

    test "it lists the notifications of the customer" do
      sign_in_as_customer(email: "buyer@example.com")
      notify(visiting_customer, "Order shipped")

      get shop_account_path

      assert_select "[data-notification]", count: 1
      assert_select "p", text: "Order shipped"
      assert_select "button", text: "Mark as read"
    end

    test "an account with no notifications says so" do
      sign_in_as_customer(email: "buyer@example.com")

      get shop_account_path

      assert_select "p", text: /Nothing yet/
    end

    test "it marks a notification read" do
      sign_in_as_customer(email: "buyer@example.com")
      notification = notify(visiting_customer, "Order shipped")

      post shop_read_notification_path(id: notification.id)

      assert_redirected_to shop_account_path
      refute_nil notification.reload.read_at
      follow_redirect!
      assert_select "button", text: "Mark as read", count: 0
    end

    test "the header counts what the customer has not read" do
      sign_in_as_customer(email: "buyer@example.com")
      notify(visiting_customer, "Order shipped")

      get root_path

      assert_select "nav a", text: "Account (1)"
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
      Notification.create!(customer: customer, subject: subject, body: "Tracking RM123.")
    end

    def signed_cookie_for(customer_id)
      jar = ActionDispatch::Cookies::CookieJar.build(request, cookies.to_hash)
      jar.signed[CustomerIdentity::COOKIE] = customer_id

      jar[CustomerIdentity::COOKIE]
    end
  end
end

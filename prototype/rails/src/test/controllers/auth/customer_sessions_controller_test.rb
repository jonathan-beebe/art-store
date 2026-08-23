require "test_helper"

module Auth
  class CustomerSessionsControllerTest < ActionDispatch::IntegrationTest
    test "the sign-in page asks for an email address in the shop layout" do
      get customer_login_path

      assert_response :success
      assert_select "body[data-site=?]", "shop"
      assert_select "form[action=?][method=?]", customer_send_magic_link_path, "post"
      assert_select "input[type=email][name=email]"
    end

    test "a signed-in customer is sent to their account instead of the form" do
      sign_in_as_customer

      get customer_login_path

      assert_redirected_to shop_account_path
    end

    test "the sign-in page holds on to where the visitor was headed" do
      get customer_login_path(redirect_to: "/orders/7/pay")

      assert_select "input[type=hidden][name=redirect_to][value=?]", "/orders/7/pay"
    end

    test "the sign-in page keeps an absolute destination on this host" do
      get customer_login_path(redirect_to: "http://www.example.com/orders/7/pay")

      assert_select "input[type=hidden][name=redirect_to][value=?]", "http://www.example.com/orders/7/pay"
    end

    test "the sign-in page drops a destination on another host" do
      assert_nil held_destination("http://evil.example/steal")
    end

    test "the sign-in page drops a host that only prefixes this one" do
      assert_nil held_destination("http://www.example.com.evil.example/steal")
    end

    test "the sign-in page drops a protocol relative destination" do
      assert_nil held_destination("//evil.example/steal")
    end

    test "the sign-in page drops a backslash escaped destination" do
      assert_nil held_destination("/\\evil.example/steal")
    end

    test "the sign-in page drops a destination carrying a newline" do
      assert_nil held_destination("/checkout\nSet-Cookie: x=1")
    end

    test "the sign-in page drops a blank destination" do
      assert_nil held_destination("   ")
    end

    test "submitting an address issues a customer link" do
      post customer_send_magic_link_path, params: { email: "buyer@example.com" }

      assert_equal "customer", MagicLink.sole.actor_type
      assert_equal "buyer@example.com", MagicLink.sole.email
    end

    test "the link carries the destination the visitor was headed for" do
      post customer_send_magic_link_path, params: { email: "buyer@example.com", redirect_to: "/orders/7/pay" }

      assert_equal "/orders/7/pay", MagicLink.sole.redirect_to
    end

    test "submitting an address that is not an email issues no link" do
      post customer_send_magic_link_path, params: { email: "not-an-address" }

      assert_response :unprocessable_content
      assert_equal 0, MagicLink.count
    end

    test "asking for a link does not sign anyone in" do
      post customer_send_magic_link_path, params: { email: "buyer@example.com" }

      assert_nil session[:customer_id]
    end

    test "the storefront header offers sign-in until someone signs in" do
      get root_path

      assert_select "header a[href=?]", customer_login_path
    end

    test "the storefront header carries a sign-out form once signed in" do
      sign_in_as_customer

      get root_path

      assert_select "header form[action=?][method=?]", customer_logout_path, "post"
    end

    test "signing out drops the customer from the session and the cookie" do
      sign_in_as_customer
      customer_id = session[:customer_id]

      post customer_logout_path

      assert_redirected_to root_path
      assert_nil session[:customer_id]
      refute_equal customer_id, signed_cookie(CustomerIdentity::COOKIE)
    end

    test "signing out leaves the next storefront visit anonymous" do
      sign_in_as_customer
      post customer_logout_path

      get root_path

      assert_predicate Customer.find(signed_cookie(CustomerIdentity::COOKIE)), :anonymous?
    end

    private

    # The value the sign-in form carries forward, or nil when the destination
    # was refused.
    def held_destination(requested)
      get customer_login_path(redirect_to: requested)

      assert_select("input[type=hidden][name=redirect_to]").sole["value"]
    end
  end
end

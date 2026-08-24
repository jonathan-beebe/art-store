require "test_helper"

module Auth
  class MagicLinksControllerTest < ActionDispatch::IntegrationTest
    test "a seller's first link creates the account and lands on the portal" do
      sign_in_as_seller(email: "newcomer@example.com")

      assert_redirected_to seller_root_path
      assert_equal "newcomer@example.com", Seller.sole.email
      assert_equal Seller.sole.id, session[:seller_id]
    end

    test "a returning seller signs in to the account already there" do
      existing = create_seller

      sign_in_as_seller(email: existing.email)

      assert_equal 1, Seller.count
      assert_equal existing.id, session[:seller_id]
    end

    test "a customer's first link creates the account and lands on the account page" do
      sign_in_as_customer(email: "newcomer@example.com")

      assert_redirected_to shop_account_path
      assert_equal "newcomer@example.com", Customer.sole.email
      assert_equal Customer.sole.id, session[:customer_id]
    end

    test "verification points the identity cookie at the verified customer" do
      sign_in_as_customer(email: "newcomer@example.com")

      assert_equal Customer.sole.id, signed_cookie(CustomerIdentity::COOKIE)
    end

    test "an admin's link signs them in and lands on the console" do
      admin = create_admin

      sign_in_as_admin(admin)

      assert_redirected_to admin_root_path
      assert_equal admin.id, session[:admin_id]
    end

    test "a link for an address no admin holds signs nobody in" do
      token, = create_magic_link(email: "stranger@example.com", actor_type: :admin)

      get verify_magic_link_path(token)

      assert_redirected_to admin_login_path
      assert_equal "That address does not reach the admin site.", flash[:alert]
      assert_nil session[:admin_id]
    end

    test "an expired admin link sends the visitor to the admin sign-in" do
      token, = create_magic_link(actor_type: :admin, expires_at: 1.minute.ago)

      get verify_magic_link_path(token)

      assert_redirected_to admin_login_path
    end

    test "a link works only once" do
      post seller_send_magic_link_path, params: { email: "artist@example.com" }
      url = flash[:debug_magic_link]
      get url

      get url

      assert_redirected_to seller_login_path
      assert_equal "That sign-in link has already been used. Ask for a new one.", flash[:alert]
    end

    test "a second visit to a used link is refused by the row-count check, not by re-reading a stale timestamp" do
      post seller_send_magic_link_path, params: { email: "artist@example.com" }
      url = flash[:debug_magic_link]
      get url
      winner = MagicLink.sole

      lines = captured_log_lines { get url }

      ending = log_lines_for("magic_link.consume", lines).last
      assert_equal "refused", ending["phase"]
      assert_equal "info", ending["level"]
      assert_equal "seller", ending["data"]["actor_type"]
      assert_equal winner.id, ending["data"]["magic_link_id"]
    end

    test "an expired, a consumed, and an unknown token all log the same refusal shape at info" do
      expired_token, = create_magic_link(expires_at: 1.minute.ago)
      consumed_token, consumed_link = create_magic_link
      consumed_link.consume
      unknown_token = "0" * 64

      [ expired_token, consumed_token, unknown_token ].each do |token|
        lines = captured_log_lines { get verify_magic_link_path(token) }

        ending = log_lines_for("magic_link.consume", lines).last
        assert_equal "refused", ending["phase"]
        assert_equal "info", ending["level"]
      end
    end

    test "the winning consume logs did, carrying the actor type and the link's own id" do
      token, link = create_magic_link

      lines = captured_log_lines { get verify_magic_link_path(token) }

      ending = log_lines_for("magic_link.consume", lines).last
      assert_equal "did", ending["phase"]
      assert_equal "seller", ending["data"]["actor_type"]
      assert_equal link.id, ending["data"]["magic_link_id"]
      assert_equal Seller.sole.id, session[:seller_id]
    end

    test "a link past its expiry signs nobody in" do
      token, = create_magic_link(expires_at: 16.minutes.ago)

      get verify_magic_link_path(token)

      assert_redirected_to seller_login_path
      assert_equal "That sign-in link has expired. Ask for a new one.", flash[:alert]
      assert_nil session[:seller_id]
    end

    test "a link used before it expired reads as used rather than expired" do
      token, link = create_magic_link(expires_at: 16.minutes.ago)
      link.consume

      get verify_magic_link_path(token)

      assert_equal "That sign-in link has already been used. Ask for a new one.", flash[:alert]
    end

    test "a link still inside the fifteen minute window works" do
      token, = create_magic_link(expires_at: 14.minutes.from_now)

      get verify_magic_link_path(token)

      assert_redirected_to seller_root_path
    end

    test "a token no link was issued for signs nobody in" do
      get verify_magic_link_path("0" * 64)

      assert_redirected_to customer_login_path
      assert_equal "That sign-in link is not valid. Ask for a new one.", flash[:alert]
    end

    test "an expired customer link sends the visitor to the storefront sign-in" do
      token, = create_magic_link(actor_type: :customer, expires_at: 1.minute.ago)

      get verify_magic_link_path(token)

      assert_redirected_to customer_login_path
    end

    test "verification carries the visitor on to the destination the link holds" do
      get root_path
      sign_in_as_customer(redirect_to: "/orders/7/pay")

      assert_redirected_to "/orders/7/pay"
    end

    test "a destination on another host is dropped in favour of the account page" do
      sign_in_as_customer(redirect_to: "http://evil.example/steal")

      assert_nil MagicLink.sole.redirect_to
      assert_redirected_to shop_account_path
    end

    test "verification keeps an absolute destination on this host" do
      follow_link_holding("http://www.example.com/orders/7/pay")

      assert_redirected_to "http://www.example.com/orders/7/pay"
    end

    test "a link holding another host lands on the portal instead" do
      follow_link_holding("http://evil.example/steal")

      assert_redirected_to seller_root_path
    end

    test "a link holding a protocol relative destination lands on the portal instead" do
      follow_link_holding("//evil.example/steal")

      assert_redirected_to seller_root_path
    end

    test "a link holding a backslash escaped destination lands on the portal instead" do
      follow_link_holding("/\\evil.example/steal")

      assert_redirected_to seller_root_path
    end

    test "a link holding a destination with a newline lands on the portal instead" do
      follow_link_holding("/checkout\nSet-Cookie: x=1")

      assert_redirected_to seller_root_path
    end

    test "an anonymous visitor with a new address claims the anonymous row in place" do
      get root_path
      anonymous_id = signed_cookie(CustomerIdentity::COOKIE)

      sign_in_as_customer(email: "newcomer@example.com")

      assert_equal 1, Customer.count
      assert_equal "newcomer@example.com", Customer.find(anonymous_id).email
      assert_equal anonymous_id, signed_cookie(CustomerIdentity::COOKIE)
    end

    test "an anonymous visitor whose address has an account merges into it" do
      existing = create_verified_customer
      get root_path
      anonymous_id = signed_cookie(CustomerIdentity::COOKIE)

      sign_in_as_customer(email: existing.email)

      assert_equal existing.id, session[:customer_id]
      assert_equal existing.id, signed_cookie(CustomerIdentity::COOKIE)
      assert_equal anonymous_id, CustomerMerge.sole.anonymous_customer_id
      assert_equal existing.id, CustomerMerge.sole.customer_id
    end

    test "a cookie left holding a merged id resolves forward to the verified customer" do
      existing = create_verified_customer
      get root_path
      stale_cookie = cookies[CustomerIdentity::COOKIE.to_s]
      sign_in_as_customer(email: existing.email)
      post customer_logout_path
      customers_before = Customer.count

      cookies[CustomerIdentity::COOKIE.to_s] = stale_cookie
      get root_path

      assert_equal existing.id, signed_cookie(CustomerIdentity::COOKIE)
      assert_equal customers_before, Customer.count
    end

    private

    # A destination that never passed through the sign-in form reaches the app
    # here, so the link is written with it in place.
    def follow_link_holding(destination)
      token, = create_magic_link(redirect_to: destination)

      get verify_magic_link_path(token)
    end
  end
end

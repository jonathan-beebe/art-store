require "identity_test_case"

module Shop
  class AccountControllerTest < IdentityIntegrationTest
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

    private

    def signed_cookie_for(customer_id)
      jar = ActionDispatch::Cookies::CookieJar.build(request, cookies.to_hash)
      jar.signed[CustomerIdentity::COOKIE] = customer_id

      jar[CustomerIdentity::COOKIE]
    end
  end
end

require "test_helper"

module Auth
  class SellerSessionsControllerTest < ActionDispatch::IntegrationTest
    test "the sign-in page asks for an email address in the seller layout" do
      get seller_login_path

      assert_response :success
      assert_select "body[data-site=?]", "seller"
      assert_select "form[action=?][method=?]", seller_send_magic_link_path, "post"
      assert_select "input[type=email][name=email]"
    end

    test "a signed-in seller is sent to the portal instead of the form" do
      sign_in_as_seller

      get seller_login_path

      assert_redirected_to seller_root_path
    end

    test "submitting an address puts a link for the issued token in the debug alert" do
      post seller_send_magic_link_path, params: { email: "artist@example.com" }
      token = flash[:debug_magic_link].split("/").last

      assert_equal verify_magic_link_url(token), flash[:debug_magic_link]
      assert_equal MagicLink.sole, MagicLink.find_by_token(token)
    end

    test "the debug alert renders the link on the page it lands on" do
      post seller_send_magic_link_path, params: { email: "artist@example.com" }
      follow_redirect!

      assert_select "[role=alert] a[href=?]", flash[:debug_magic_link]
    end

    test "the link is issued for the seller side" do
      post seller_send_magic_link_path, params: { email: "artist@example.com" }

      assert_equal "seller", MagicLink.sole.actor_type
    end

    test "submitting an address that is not an email issues no link" do
      post seller_send_magic_link_path, params: { email: "not-an-address" }

      assert_response :unprocessable_content
      assert_equal 0, MagicLink.count
    end

    test "the seller header offers sign-in until someone signs in" do
      get seller_login_path

      assert_select "header a[href=?]", seller_login_path
    end

    test "the seller header carries a sign-out form once signed in" do
      sign_in_as_seller

      get seller_root_path

      assert_select "header form[action=?][method=?]", seller_logout_path, "post"
    end

    test "signing out drops the seller from the session" do
      sign_in_as_seller

      post seller_logout_path

      assert_redirected_to seller_login_path
      assert_nil session[:seller_id]
    end
  end
end

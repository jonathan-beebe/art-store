require "test_helper"

module Auth
  class AdminSessionsControllerTest < ActionDispatch::IntegrationTest
    test "the sign-in page asks for an email address in the admin layout" do
      get admin_login_path

      assert_response :success
      assert_select "body[data-site=?]", "admin"
      assert_select "form[action=?][method=?]", admin_send_magic_link_path, "post"
      assert_select "input[type=email][name=email]"
    end

    test "a signed-in admin is sent to the console instead of the form" do
      sign_in_as_admin

      get admin_login_path

      assert_redirected_to admin_root_path
    end

    test "submitting an address puts a link for the issued token in the debug alert" do
      post admin_send_magic_link_path, params: { email: "ops@example.com" }
      token = flash[:debug_magic_link].split("/").last

      assert_equal verify_magic_link_url(token), flash[:debug_magic_link]
      assert_equal MagicLink.sole, MagicLink.find_by_token(token)
    end

    test "the link is issued for the admin side" do
      post admin_send_magic_link_path, params: { email: "ops@example.com" }

      assert_equal "admin", MagicLink.sole.actor_type
    end

    test "submitting an address that is not an email issues no link" do
      assert_no_enqueued_emails do
        post admin_send_magic_link_path, params: { email: "not-an-address" }
      end

      assert_response :unprocessable_content
      assert_equal 0, MagicLink.count
    end

    test "the admin header offers sign-in until someone signs in" do
      get admin_login_path

      assert_select "header a[href=?]", admin_login_path
    end

    test "the admin header carries a sign-out form once signed in" do
      sign_in_as_admin

      get admin_root_path

      assert_select "header form[action=?][method=?]", admin_logout_path, "post"
    end

    test "signing out drops the admin from the session" do
      sign_in_as_admin

      post admin_logout_path

      assert_redirected_to admin_login_path
      assert_nil session[:admin_id]
    end
  end
end

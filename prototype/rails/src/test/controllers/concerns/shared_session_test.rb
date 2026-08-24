require "test_helper"

# One browser holding all three actors at once: a customer, a seller, and an
# admin signed in together, each under their own session key, so a reviewer
# can demo all three side by side.
class SharedSessionTest < ActionDispatch::IntegrationTest
  test "signing in as a seller, then a customer, then an admin leaves all three signed in" do
    seller = signed_in_seller
    sign_in_as_customer
    customer = visiting_customer
    admin = sign_in_as_admin

    assert_equal seller.id, session[:seller_id]
    assert_equal customer.id, session[:customer_id]
    assert_equal admin.id, session[:admin_id]

    get seller_root_path
    assert_response :success
    get shop_account_path
    assert_response :success
    get admin_root_path
    assert_response :success
  end

  test "signing out of the customer leaves the seller and the admin signed in" do
    seller = signed_in_seller
    sign_in_as_customer
    admin = sign_in_as_admin

    post customer_logout_path

    assert_nil session[:customer_id]
    assert_equal seller.id, session[:seller_id]
    assert_equal admin.id, session[:admin_id]
  end

  test "signing out of the seller leaves the customer and the admin signed in" do
    sign_in_as_customer
    admin = sign_in_as_admin
    signed_in_seller

    post seller_logout_path

    assert_nil session[:seller_id]
    assert_not_nil session[:customer_id]
    assert_equal admin.id, session[:admin_id]
  end

  test "signing out of the admin leaves the customer and the seller signed in" do
    seller = signed_in_seller
    sign_in_as_customer
    sign_in_as_admin

    post admin_logout_path

    assert_nil session[:admin_id]
    assert_equal seller.id, session[:seller_id]
    assert_not_nil session[:customer_id]
  end

  test "each sign-in rotates the session id" do
    sign_in_as_admin
    before_seller = settled_session_id

    signed_in_seller
    after_seller = settled_session_id

    sign_in_as_customer
    after_customer = settled_session_id

    assert_not_equal before_seller, after_seller
    assert_not_equal after_seller, after_customer
  end

  test "the session id rotates on sign-in without losing what it already held" do
    seller = signed_in_seller
    id_before = settled_session_id

    sign_in_as_customer
    id_after = settled_session_id

    assert_not_equal id_before, id_after
    assert_equal seller.id, session[:seller_id]
  end

  test "the sid cookie is unchanged by any of the three actors signing in" do
    get root_path
    minted = cookies[:sid]

    signed_in_seller
    assert_equal minted, cookies[:sid]

    sign_in_as_customer
    assert_equal minted, cookies[:sid]

    sign_in_as_admin
    assert_equal minted, cookies[:sid]
  end

  test "the sid cookie is unchanged by any of the three actors signing out" do
    get root_path
    minted = cookies[:sid]
    signed_in_seller
    sign_in_as_customer
    sign_in_as_admin

    post seller_logout_path
    assert_equal minted, cookies[:sid]

    post customer_logout_path
    assert_equal minted, cookies[:sid]

    post admin_logout_path
    assert_equal minted, cookies[:sid]
  end

  private

  # `request.session.id` reflects the id that was on the incoming cookie when
  # the request it is read from began. `Rack::Session::Abstract::Persisted
  # #commit_session` writes a renewed id into the outgoing `Set-Cookie`, but
  # never back into that request's own session object, so reading it from the
  # same request a sign-in happened in cannot show whether the sign-in
  # rotated anything. A following request picks up the cookie the previous
  # response actually sent, so its freshly loaded id is the settled one.
  def settled_session_id
    get root_path
    request.session.id.to_s
  end
end

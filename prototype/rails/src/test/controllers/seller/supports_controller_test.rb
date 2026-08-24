require "test_helper"

class Seller::SupportsControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor reaches no support thread" do
    post seller_support_path

    assert_redirected_to seller_login_path
  end

  test "the button opens the thread with the operator on duty" do
    seller = signed_in_seller
    desk = create_admin
    create_admin(email: "later@example.test")

    post seller_support_path

    conversation = Conversation.sole
    assert_redirected_to seller_conversation_path(conversation)
    assert_equal desk, conversation.admin
    assert_equal seller, conversation.seller
  end

  test "pressing it again reaches the thread already open" do
    signed_in_seller
    create_admin

    post seller_support_path
    post seller_support_path

    assert_equal 1, Conversation.count
  end

  test "with nobody on the desk the button says so and opens nothing" do
    signed_in_seller

    post seller_support_path

    assert_redirected_to seller_root_path
    assert_equal "Nobody is on the support desk yet.", flash[:alert]
    assert_empty Conversation.all
  end

  test "the dashboard carries the button" do
    signed_in_seller

    get seller_root_path

    assert_select "form[action=?][method=post] button", seller_support_path, text: "Support"
  end
end

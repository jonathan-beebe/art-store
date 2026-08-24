require "test_helper"

class Admin::SellerConversationsControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor opens no thread" do
    post admin_seller_conversation_path(create_seller)

    assert_redirected_to admin_login_path
  end

  test "the seller page opens the support thread with that seller" do
    admin = sign_in_as_admin
    seller = create_seller

    post admin_seller_conversation_path(seller)

    conversation = Conversation.sole
    assert_redirected_to admin_conversation_path(conversation)
    assert_equal admin, conversation.admin
    assert_equal seller, conversation.seller
  end

  test "a second press reaches the thread already open" do
    sign_in_as_admin
    seller = create_seller

    post admin_seller_conversation_path(seller)
    post admin_seller_conversation_path(seller)

    assert_equal 1, Conversation.count
  end

  test "a seller id nothing was written for opens no thread" do
    sign_in_as_admin

    post admin_seller_conversation_path(seller_id: 0)

    assert_response :not_found
    assert_empty Conversation.all
  end

  test "the seller page carries the button" do
    sign_in_as_admin
    seller = create_seller

    get admin_seller_path(seller)

    assert_select "form[action=?][method=post] button", admin_seller_conversation_path(seller), text: "Message"
  end
end

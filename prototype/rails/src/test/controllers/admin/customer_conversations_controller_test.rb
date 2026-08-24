require "test_helper"

class Admin::CustomerConversationsControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor opens no thread" do
    post admin_customer_conversation_path(create_verified_customer)

    assert_redirected_to admin_login_path
  end

  test "the customer page opens the support thread with that customer" do
    admin = sign_in_as_admin
    customer = create_verified_customer

    post admin_customer_conversation_path(customer)

    conversation = Conversation.sole
    assert_redirected_to admin_conversation_path(conversation)
    assert_equal admin, conversation.admin
    assert_equal customer, conversation.customer
  end

  test "a second press reaches the thread already open" do
    sign_in_as_admin
    customer = create_verified_customer

    post admin_customer_conversation_path(customer)
    post admin_customer_conversation_path(customer)

    assert_equal 1, Conversation.count
  end

  test "a visitor who has given no address opens no thread" do
    sign_in_as_admin

    post admin_customer_conversation_path(create_anonymous_customer)

    assert_response :not_found
    assert_empty Conversation.all
  end

  test "the customer page carries the button" do
    sign_in_as_admin
    customer = create_verified_customer

    get admin_customer_path(customer)

    assert_select "form[action=?][method=post] button", admin_customer_conversation_path(customer), text: "Message"
  end
end

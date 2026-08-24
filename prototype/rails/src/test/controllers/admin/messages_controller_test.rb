require "test_helper"

class Admin::MessagesControllerTest < ActionDispatch::IntegrationTest
  test "a reply lands in the thread and returns to it" do
    admin = sign_in_as_admin
    thread = support_thread(admin)

    post admin_conversation_messages_path(thread), params: { message: { body: "Looking into it." } }

    assert_redirected_to admin_conversation_path(thread)
    assert_equal "Looking into it.", thread.messages.sole.body
    assert_equal admin, thread.messages.sole.sender
  end

  test "the seller is notified with the thread on their own site" do
    admin = sign_in_as_admin
    thread = support_thread(admin)

    post admin_conversation_messages_path(thread), params: { message: { body: "Looking into it." } }

    notification = thread.seller.notifications.sole
    assert_equal "New message", notification.subject
    assert_equal seller_conversation_path(thread), notification.url
  end

  test "an empty reply comes back on the thread with the refusal" do
    admin = sign_in_as_admin
    thread = support_thread(admin)

    post admin_conversation_messages_path(thread), params: { message: { body: "" } }

    assert_response :unprocessable_content
    assert_select "[data-field-error=?]", "message_body", text: "Write a message."
    assert_empty thread.messages
  end

  test "a thread the operator is not in takes no reply" do
    sign_in_as_admin
    thread = support_thread(create_admin(email: "other@example.test"))

    post admin_conversation_messages_path(thread), params: { message: { body: "Hello." } }

    assert_response :not_found
    assert_empty thread.messages
  end

  private

  def support_thread(admin)
    Conversation.open(kind: :admin_seller, admin: admin, seller: create_seller)
  end
end

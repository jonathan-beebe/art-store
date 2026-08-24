require "test_helper"

class Seller::MessagesControllerTest < ActionDispatch::IntegrationTest
  test "a reply lands in the thread and returns to it" do
    seller = signed_in_seller
    thread = fulfillment_thread(seller)

    post seller_conversation_messages_path(thread), params: { message: { body: "It ships tomorrow." } }

    assert_redirected_to seller_conversation_path(thread)
    assert_equal "It ships tomorrow.", thread.messages.sole.body
    assert_equal seller, thread.messages.sole.sender
  end

  test "the customer is notified with the thread on their own site" do
    seller = signed_in_seller
    thread = fulfillment_thread(seller)

    post seller_conversation_messages_path(thread), params: { message: { body: "It ships tomorrow." } }

    notification = thread.customer.notifications.sole
    assert_equal "New message", notification.subject
    assert_equal shop_conversation_path(thread), notification.url
  end

  test "an empty reply comes back on the thread with the refusal" do
    seller = signed_in_seller
    thread = fulfillment_thread(seller)

    post seller_conversation_messages_path(thread), params: { message: { body: "  " } }

    assert_response :unprocessable_content
    assert_select "[data-field-error=?]", "message_body", text: "Write a message."
    assert_empty thread.messages
    assert_empty thread.customer.notifications
  end

  test "a thread the seller is not in takes no reply" do
    signed_in_seller
    thread = fulfillment_thread(other_seller)

    post seller_conversation_messages_path(thread), params: { message: { body: "Hello." } }

    assert_response :not_found
    assert_empty thread.messages
  end

  private

  def fulfillment_thread(seller)
    fulfillment = create_fulfillment(seller)

    Conversation.open(
      kind: :fulfillment, subject: fulfillment,
      seller: seller, customer: fulfillment.order.customer
    )
  end
end

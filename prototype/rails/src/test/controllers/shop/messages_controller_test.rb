require "test_helper"

module Shop
  class MessagesControllerTest < ActionDispatch::IntegrationTest
    test "a reply lands in the thread and returns to it" do
      thread = listing_question

      post shop_conversation_messages_path(thread), params: { message: { body: "Is this still available?" } }

      assert_redirected_to shop_conversation_path(thread)
      assert_equal "Is this still available?", thread.messages.sole.body
      assert_equal thread.customer, thread.messages.sole.sender
    end

    test "the seller is notified with the thread on their own site" do
      thread = listing_question

      post shop_conversation_messages_path(thread), params: { message: { body: "Is this still available?" } }

      notification = thread.seller.notifications.sole
      assert_equal "New message", notification.subject
      assert_equal seller_conversation_path(thread), notification.url
    end

    test "an empty reply comes back on the thread with the refusal" do
      thread = listing_question

      post shop_conversation_messages_path(thread), params: { message: { body: "" } }

      assert_response :unprocessable_content
      assert_select "[data-field-error=?]", "message_body", text: "Write a message."
      assert_empty thread.messages
    end

    test "a thread the customer is not in takes no reply" do
      sign_in_as_customer(email: "stranger@example.com")
      thread = Conversation.open(
        kind: :admin_customer, admin: create_admin, customer: create_verified_customer
      )

      post shop_conversation_messages_path(thread), params: { message: { body: "Hello." } }

      assert_response :not_found
      assert_empty thread.messages
    end

    test "a blocked customer cannot reply, but still reads the thread" do
      thread = listing_question
      visiting_customer.block!(reason: "Chargeback fraud.", by: create_admin)

      post shop_conversation_messages_path(thread), params: { message: { body: "Is this still available?" } }

      assert_response :unprocessable_content
      assert_select "[data-flash=alert]", text: "This account is blocked and cannot send messages."
      assert_empty thread.messages

      get shop_conversation_path(thread)

      assert_response :success
    end

    private

    # A thread the storefront's current visitor is in, opened before they say
    # anything in it.
    def listing_question
      sign_in_as_customer(email: "buyer@example.com")
      seller = create_seller

      Conversation.open(
        kind: :listing_question, seller: seller, customer: visiting_customer,
        subject: create_listing(seller)
      )
    end
  end
end

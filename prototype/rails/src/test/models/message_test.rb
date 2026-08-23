require "test_helper"

class MessageTest < ActiveSupport::TestCase
  test "a message reads back under the sender that wrote it" do
    shop = create_seller
    conversation = support_thread(shop)

    message = conversation.post!(shop, "My payout is late.")

    assert_equal [message], shop.sent_messages.to_a
  end

  test "surrounding whitespace is trimmed on the way in" do
    shop = create_seller

    message = support_thread(shop).post!(shop, "  My payout is late.\n")

    assert_equal "My payout is late.", message.body
  end

  test "an empty message is invalid" do
    message = Message.new(conversation: support_thread(create_seller), sender: create_admin, body: "   ")

    assert_predicate message, :invalid?
    assert_includes message.errors.attribute_names, :body
  end

  test "a body at the limit is valid and one over it is not" do
    conversation = support_thread(create_seller)
    admin = conversation.admin

    assert_predicate Message.new(conversation: conversation, sender: admin, body: "a" * 2_000), :valid?
    assert_predicate Message.new(conversation: conversation, sender: admin, body: "a" * 2_001), :invalid?
  end

  test "a message is unread for everyone but its sender until it is read" do
    shop = create_seller
    conversation = support_thread(shop)
    message = conversation.post!(shop, "My payout is late.")

    assert_includes Message.unread_for(conversation.admin), message
    assert_not_includes Message.unread_for(shop), message

    message.update!(read_at: Time.current)

    assert_not_includes Message.unread_for(conversation.admin), message
  end

  test "a thread reads oldest first" do
    shop = create_seller
    conversation = support_thread(shop)
    asked = conversation.post!(shop, "My payout is late.")
    answered = conversation.post!(conversation.admin, "It runs on Monday.")

    assert_equal [asked, answered], conversation.messages.oldest_first.to_a
  end

  private

  def support_thread(seller)
    Conversation.open(kind: :admin_seller, admin: create_admin, seller: seller)
  end
end

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

  test "an arriving message reaches both sides' open thread pages" do
    shop = create_seller
    conversation = support_thread(shop)

    appended = capture_turbo_stream_broadcasts([conversation, shop]) do
      conversation.post!(shop, "My payout is late.")
    end

    assert_equal 1, appended.count
    assert_equal "append", appended.sole["action"]
    assert_equal conversation.messages_dom_id, appended.sole["target"]
    assert_includes appended.sole.to_html, "My payout is late."
  end

  test "each side is sent the markup of the site it reads on" do
    shop = create_seller
    conversation = support_thread(shop)
    admin = conversation.admin

    to_admin = nil
    to_seller = capture_turbo_stream_broadcasts([conversation, shop]) do
      to_admin = capture_turbo_stream_broadcasts([conversation, admin]) do
        conversation.post!(shop, "My payout is late.")
      end
    end

    assert_includes to_seller.sole.to_html, "bg-white"
    assert_includes to_admin.sole.to_html, "bg-slate-800"
  end

  test "the side that did not write the message is sent a new badge" do
    shop = create_seller
    conversation = support_thread(shop)
    admin = conversation.admin

    replaced = capture_turbo_stream_broadcasts([admin, :unread_messages]) do
      conversation.post!(shop, "My payout is late.")
    end

    assert_equal "replace", replaced.sole["action"]
    assert_equal admin.unread_badge_dom_id, replaced.sole["target"]
    assert_match(/>1</, replaced.sole.to_html)
  end

  test "the writer's own badge stays where it was" do
    shop = create_seller
    conversation = support_thread(shop)

    assert_turbo_stream_broadcasts([shop, :unread_messages], count: 0) do
      conversation.post!(shop, "My payout is late.")
    end
  end

  test "a post whose transaction rolls back broadcasts nothing" do
    shop = create_seller
    conversation = support_thread(shop)
    admin = conversation.admin

    assert_turbo_stream_broadcasts([conversation, admin], count: 0) do
      assert_turbo_stream_broadcasts([admin, :unread_messages], count: 0) do
        conversation.transaction do
          conversation.post!(shop, "My payout is late.")
          raise ActiveRecord::Rollback
        end
      end
    end

    assert_equal 0, Message.count
  end

  private

  def support_thread(seller)
    Conversation.open(kind: :admin_seller, admin: create_admin, seller: seller)
  end
end

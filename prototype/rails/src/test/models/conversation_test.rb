require "test_helper"

class ConversationTest < ActiveSupport::TestCase
  test "a support thread with a seller names the admin and the seller" do
    admin = create_admin
    shop = create_seller

    conversation = Conversation.open(kind: :admin_seller, admin: admin, seller: shop)

    assert_equal admin, conversation.admin
    assert_equal shop, conversation.seller
    assert_nil conversation.customer
    assert_nil conversation.subject
  end

  test "a support thread with a customer names the admin and the customer" do
    admin = create_admin
    buyer = create_verified_customer

    conversation = Conversation.open(kind: :admin_customer, admin: admin, customer: buyer)

    assert_equal admin, conversation.admin
    assert_equal buyer, conversation.customer
    assert_nil conversation.seller
  end

  test "a thread about an order names the seller, the customer and the fulfillment" do
    shop = create_seller
    buyer = create_verified_customer
    fulfillment = fulfillment_for(shop, buyer)

    conversation = Conversation.open(kind: :fulfillment, seller: shop, customer: buyer, subject: fulfillment)

    assert_equal shop, conversation.seller
    assert_equal buyer, conversation.customer
    assert_equal fulfillment, conversation.subject
    assert_nil conversation.admin
  end

  test "a question about a listing names the seller, the customer and the listing" do
    shop = create_seller
    listing = create_listing(shop)
    buyer = create_anonymous_customer

    conversation = Conversation.open(kind: :listing_question, seller: shop, customer: buyer, subject: listing)

    assert_equal listing, conversation.subject
    assert_equal buyer, conversation.customer
  end

  test "opening the same kind, participants and subject twice reaches the one thread" do
    shop = create_seller
    listing = create_listing(shop)
    buyer = create_verified_customer

    opened = Conversation.open(kind: :listing_question, seller: shop, customer: buyer, subject: listing)
    reopened = Conversation.open(kind: :listing_question, seller: shop, customer: buyer, subject: listing)

    assert_equal opened, reopened
    assert_equal 1, Conversation.count
  end

  test "a question about another listing opens its own thread" do
    shop = create_seller
    buyer = create_verified_customer
    first = Conversation.open(kind: :listing_question, seller: shop, customer: buyer, subject: create_listing(shop))

    second = Conversation.open(kind: :listing_question, seller: shop, customer: buyer, subject: create_listing(shop))

    assert_not_equal first, second
  end

  test "a support thread opened by each side reaches the one thread" do
    admin = create_admin
    shop = create_seller

    opened = Conversation.open(kind: :admin_seller, admin: admin, seller: shop)
    reopened = Conversation.open(kind: :admin_seller, seller: shop, admin: admin)

    assert_equal opened, reopened
  end

  test "a thread opens at the moment it is opened" do
    conversation = Conversation.open(
      kind: :admin_seller, admin: create_admin, seller: create_seller, at: moment("2026-08-21 09:00:00")
    )

    assert_equal moment("2026-08-21 09:00:00"), conversation.last_message_at
  end

  test "a kind no conversation has is invalid" do
    conversation = Conversation.new(kind: "seller_seller", last_message_at: Time.current)

    assert_predicate conversation, :invalid?
    assert_includes conversation.errors.attribute_names, :kind
  end

  test "a support thread missing its admin is invalid" do
    conversation = Conversation.new(kind: "admin_seller", seller: create_seller, last_message_at: Time.current)

    assert_predicate conversation, :invalid?
    assert_includes conversation.errors.attribute_names, :admin
  end

  test "a support thread naming a third participant is invalid" do
    conversation = Conversation.new(
      kind: "admin_seller", admin: create_admin, seller: create_seller,
      customer: create_verified_customer, last_message_at: Time.current
    )

    assert_predicate conversation, :invalid?
    assert_includes conversation.errors.attribute_names, :customer
  end

  test "a support thread about a subject is invalid" do
    conversation = Conversation.new(
      kind: "admin_seller", admin: create_admin, seller: create_seller,
      subject: create_listing, last_message_at: Time.current
    )

    assert_predicate conversation, :invalid?
    assert_includes conversation.errors.attribute_names, :subject
  end

  test "a listing question about a fulfillment is invalid" do
    shop = create_seller
    buyer = create_verified_customer
    conversation = Conversation.new(
      kind: "listing_question", seller: shop, customer: buyer,
      subject: fulfillment_for(shop, buyer), last_message_at: Time.current
    )

    assert_predicate conversation, :invalid?
    assert_includes conversation.errors.attribute_names, :subject
  end

  test "a thread about an order with no fulfillment is invalid" do
    conversation = Conversation.new(
      kind: "fulfillment", seller: create_seller, customer: create_verified_customer,
      last_message_at: Time.current
    )

    assert_predicate conversation, :invalid?
    assert_includes conversation.errors.attribute_names, :subject
  end

  test "both sides are participants and a stranger is not" do
    shop = create_seller
    buyer = create_verified_customer
    conversation = listing_question(shop, buyer)

    assert conversation.participant?(shop)
    assert conversation.participant?(buyer)
    assert_not conversation.participant?(create_verified_customer)
    assert_not conversation.participant?(create_admin)
  end

  test "the counterpart of each side is the other one" do
    shop = create_seller
    buyer = create_verified_customer
    conversation = listing_question(shop, buyer)

    assert_equal buyer, conversation.counterpart_of(shop)
    assert_equal shop, conversation.counterpart_of(buyer)
  end

  test "an actor's threads read newest first" do
    shop = create_seller
    buyer = create_verified_customer
    older = listing_question(shop, buyer, at: moment("2026-08-20 09:00:00"))
    newer = listing_question(shop, buyer, at: moment("2026-08-21 09:00:00"))

    assert_equal [newer, older], Conversation.involving(shop).to_a
    assert_equal [newer, older], Conversation.involving(buyer).to_a
  end

  test "the threads an actor is in leave out the ones they are not" do
    shop = create_seller
    conversation = listing_question(shop, create_verified_customer)

    assert_not_includes Conversation.involving(create_verified_customer), conversation
    assert_not_includes Conversation.involving(create_seller), conversation
    assert_not_includes Conversation.involving(create_admin), conversation
  end

  test "posting appends the message under its sender" do
    shop = create_seller
    buyer = create_verified_customer
    conversation = listing_question(shop, buyer)

    message = conversation.post!(buyer, "Is the frame included?")

    assert_equal [message], conversation.messages.oldest_first.to_a
    assert_equal buyer, message.sender
    assert_equal "Is the frame included?", message.body
  end

  test "posting moves the thread to the top of both inboxes" do
    shop = create_seller
    buyer = create_verified_customer
    older = listing_question(shop, buyer, at: moment("2026-08-20 09:00:00"))
    listing_question(shop, buyer, at: moment("2026-08-21 09:00:00"))

    older.post!(buyer, "Still available?", at: moment("2026-08-22 09:00:00"))

    assert_equal older, Conversation.involving(shop).first
    assert_equal older, Conversation.involving(buyer).first
    assert_equal moment("2026-08-22 09:00:00"), older.reload.last_message_at
  end

  test "posting tells the counterpart what the thread is about" do
    shop = create_seller
    listing = create_listing(shop, title: "Harbour at Dusk")
    buyer = create_verified_customer
    conversation = Conversation.open(kind: :listing_question, seller: shop, customer: buyer, subject: listing)

    conversation.post!(buyer, "Is the frame included?")

    notification = Notification.sole

    assert_equal shop, notification.recipient
    assert_equal "New message", notification.subject
    assert_equal "You have a new message about “Harbour at Dusk”.", notification.body
  end

  test "posting points the counterpart at their own thread page" do
    shop = create_seller
    buyer = create_verified_customer
    admin = create_admin
    seller_thread = listing_question(shop, buyer)
    support_thread = Conversation.open(kind: :admin_customer, admin: admin, customer: buyer)

    seller_thread.post!(buyer, "Is the frame included?")
    seller_thread.post!(shop, "It is.")
    support_thread.post!(admin, "Welcome.")

    assert_equal(
      ["/seller/messages/#{seller_thread.id}", "/messages/#{seller_thread.id}", "/messages/#{support_thread.id}"],
      Notification.order(:id).pluck(:url)
    )
  end

  test "posting to a support thread points the admin at the admin site" do
    admin = create_admin
    shop = create_seller
    conversation = Conversation.open(kind: :admin_seller, admin: admin, seller: shop)

    conversation.post!(shop, "My payout is late.")

    assert_equal "/admin/messages/#{conversation.id}", Notification.sole.url
  end

  test "a message from someone outside the thread is refused" do
    conversation = listing_question(create_seller, create_verified_customer)

    assert_raises(ArgumentError) { conversation.post!(create_admin, "Let me in.") }
  end

  test "a body over the limit leaves the thread as it was" do
    shop = create_seller
    buyer = create_verified_customer
    conversation = listing_question(shop, buyer, at: moment("2026-08-20 09:00:00"))

    assert_raises(ActiveRecord::RecordInvalid) { conversation.post!(buyer, "a" * 2_001) }

    assert_empty conversation.messages.reload
    assert_empty Notification.all
    assert_equal moment("2026-08-20 09:00:00"), conversation.reload.last_message_at
  end

  test "the unread count for a side counts what the other side sent" do
    shop = create_seller
    buyer = create_verified_customer
    conversation = listing_question(shop, buyer)

    conversation.post!(buyer, "Is the frame included?")
    conversation.post!(buyer, "And does it ship rolled?")

    assert_equal 2, conversation.unread_count_for(shop)
    assert_equal 0, conversation.unread_count_for(buyer)
  end

  test "reading a thread zeroes the reader's count and leaves their own messages alone" do
    shop = create_seller
    buyer = create_verified_customer
    conversation = listing_question(shop, buyer)
    asked = conversation.post!(buyer, "Is the frame included?")
    answered = conversation.post!(shop, "It is.")

    conversation.read_by!(shop, at: moment("2026-08-22 09:00:00"))

    assert_equal 0, conversation.unread_count_for(shop)
    assert_equal moment("2026-08-22 09:00:00"), asked.reload.read_at
    assert_nil answered.reload.read_at
    assert_equal 1, conversation.unread_count_for(buyer)
  end

  test "reading a thread sends the reader a badge with what is left" do
    shop = create_seller
    buyer = create_verified_customer
    conversation = listing_question(shop, buyer)
    conversation.post!(buyer, "Is the frame included?")
    listing_question(shop, buyer, title: "Meadow at Low Tide").post!(buyer, "And this one?")

    replaced = capture_turbo_stream_broadcasts([shop, :unread_messages]) do
      conversation.read_by!(shop)
    end

    assert_equal "replace", replaced.sole["action"]
    assert_equal shop.unread_badge_dom_id, replaced.sole["target"]
    assert_match(/>1</, replaced.sole.to_html)
  end

  test "a thread with nothing unread on it sends no badge" do
    shop = create_seller
    conversation = listing_question(shop, create_verified_customer)

    assert_turbo_stream_broadcasts([shop, :unread_messages], count: 0) do
      conversation.read_by!(shop)
    end
  end

  test "a reading that rolls back sends no badge" do
    shop = create_seller
    buyer = create_verified_customer
    conversation = listing_question(shop, buyer)
    conversation.post!(buyer, "Is the frame included?")

    assert_turbo_stream_broadcasts([shop, :unread_messages], count: 0) do
      conversation.transaction do
        conversation.read_by!(shop)
        raise ActiveRecord::Rollback
      end
    end
  end

  test "a support thread is about the desk" do
    admin = create_admin

    assert_equal "Art Store support", Conversation.open(kind: :admin_seller, admin: admin, seller: create_seller).topic
    assert_equal(
      "Art Store support",
      Conversation.open(kind: :admin_customer, admin: admin, customer: create_verified_customer).topic
    )
  end

  test "a thread about an order is about the order the customer placed" do
    shop = create_seller
    buyer = create_verified_customer
    fulfillment = fulfillment_for(shop, buyer)

    conversation = Conversation.open(kind: :fulfillment, seller: shop, customer: buyer, subject: fulfillment)

    assert_equal "order ##{fulfillment.order_id}", conversation.topic
  end

  test "a question is about the listing it was asked on" do
    shop = create_seller
    conversation = listing_question(shop, create_verified_customer, title: "Harbour at Dusk")

    assert_equal "“Harbour at Dusk”", conversation.topic
  end

  test "the latest message from a side is the last thing that side said" do
    shop = create_seller
    buyer = create_verified_customer
    conversation = listing_question(shop, buyer)
    conversation.post!(buyer, "Is the frame included?", at: moment("2026-08-21 09:00:00"))
    conversation.post!(shop, "It is.", at: moment("2026-08-21 10:00:00"))
    latest = conversation.post!(buyer, "What wood?", at: moment("2026-08-21 11:00:00"))

    assert_equal latest, conversation.latest_message_from(buyer)
    assert_equal "It is.", conversation.latest_message_from(shop).body
  end

  test "a side that has said nothing has no latest message" do
    buyer = create_verified_customer
    conversation = listing_question(create_seller, buyer)

    assert_nil conversation.latest_message_from(buyer)
  end

  test "a thread carries the messages it holds away with it" do
    buyer = create_verified_customer
    conversation = listing_question(create_seller, buyer)
    conversation.post!(buyer, "Is the frame included?")

    conversation.destroy!

    assert_equal 0, Message.count
  end

  private

  def listing_question(seller, customer, title: "Harbour at Dusk", at: Time.current)
    Conversation.open(
      kind: :listing_question, seller: seller, customer: customer,
      subject: create_listing(seller, title: title), at: at
    )
  end

  def fulfillment_for(seller, customer)
    order_for(customer, create_listing(seller)).fulfillments.sole
  end
end

require "test_helper"

class Seller::ConversationsControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor reaches no inbox and no thread" do
    get seller_conversations_path
    assert_redirected_to seller_login_path

    get seller_conversation_path(id: 1)
    assert_redirected_to seller_login_path
  end

  test "the inbox lists the seller's threads newest first" do
    seller = signed_in_seller
    desk = Conversation.open(
      kind: :admin_seller, admin: create_admin(name: "Ops"), seller: seller,
      at: moment("2026-08-20 09:00:00")
    )
    question = listing_question(seller, at: moment("2026-08-21 09:00:00"))

    get seller_conversations_path

    assert_response :success
    assert_select "[data-conversation]", 2
    assert_select "[data-conversation]:first-of-type", text: /Ada Lovelace/
    assert_select "[data-conversation=?] [data-cell=topic]", question.id.to_s, text: "“Harbour at Dusk”"
    assert_select "[data-conversation=?] [data-cell=topic]", desk.id.to_s, text: "Art Store support"
  end

  test "an inbox row counts what this side has not read" do
    seller = signed_in_seller
    question = listing_question(seller)
    question.post!(question.customer, "Is this still available?")
    question.post!(seller, "It is.")

    get seller_conversations_path

    assert_select "[data-conversation=?] [data-unread-count=?]", question.id.to_s, "1"
    assert_select "[data-conversation=?]", question.id.to_s, text: /1 unread/
  end

  test "another seller's threads stay off the inbox" do
    signed_in_seller
    listing_question(other_seller)

    get seller_conversations_path

    assert_select "[data-conversation]", false
    assert_select "p", text: "Nothing yet."
  end

  test "the thread reads oldest message first and names who wrote each" do
    seller = signed_in_seller
    question = listing_question(seller)
    question.post!(question.customer, "Is this still available?", at: moment("2026-08-21 09:00:00"))
    question.post!(seller, "It is.", at: moment("2026-08-21 10:00:00"))

    get seller_conversation_path(question)

    assert_response :success
    assert_select "h1", text: "“Harbour at Dusk”"
    assert_select "[data-cell=counterpart]", text: /Ada Lovelace/
    assert_select "[data-message]", 2
    assert_select "[data-message]:first-of-type", text: /Is this still available\?/
    assert_select "[data-message]:first-of-type", text: /Ada Lovelace/
    assert_select "form[action=?][method=post]", seller_conversation_messages_path(question)
  end

  test "opening a thread reads what the other side sent" do
    seller = signed_in_seller
    question = listing_question(seller)
    question.post!(question.customer, "Is this still available?")

    get seller_conversation_path(question)

    assert_equal 0, question.unread_count_for(seller)
    assert_select "[data-unread-messages]", false
  end

  test "the nav counts what is unread across threads" do
    seller = signed_in_seller
    question = listing_question(seller)
    question.post!(question.customer, "Anyone there?")

    get seller_root_path

    assert_select "[data-unread-messages]", text: "1"
  end

  test "a thread the seller is not in is not found" do
    signed_in_seller

    get seller_conversation_path(listing_question(other_seller))

    assert_response :not_found
  end

  test "an id no thread carries is not found" do
    signed_in_seller

    get seller_conversation_path(id: "not-a-number")

    assert_response :not_found
  end

  private

  # The seller's side of a question a customer asked about one of their
  # listings: the kind that names both of them and a subject.
  def listing_question(seller, at: Time.current)
    Conversation.open(
      kind: :listing_question, seller: seller,
      customer: create_verified_customer(name: "Ada Lovelace"),
      subject: create_listing(seller, title: "Harbour at Dusk"), at: at
    )
  end
end

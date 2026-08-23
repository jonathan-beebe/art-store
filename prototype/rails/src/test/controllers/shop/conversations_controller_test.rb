require "test_helper"

module Shop
  class ConversationsControllerTest < ActionDispatch::IntegrationTest
    test "a visitor who has given no address still has an inbox" do
      get shop_conversations_path

      assert_response :success
      assert_select "p", text: /Nothing here yet/
    end

    test "the inbox lists the customer's threads newest first" do
      customer = visiting_customer_after_sign_in
      older = support_thread(customer, at: moment("2026-08-20 09:00:00"))
      question = listing_question(customer, at: moment("2026-08-21 09:00:00"))

      get shop_conversations_path

      assert_response :success
      assert_select "[data-conversation]", 2
      assert_select "[data-conversation]:first-of-type", text: /Blue Kiln Studio/
      assert_select "[data-conversation=?] [data-cell=topic]", question.id.to_s, text: "“Harbour at Dusk”"
      assert_select "[data-conversation=?] [data-cell=topic]", older.id.to_s, text: "Art Store support"
    end

    test "an inbox row counts what this side has not read" do
      customer = visiting_customer_after_sign_in
      question = listing_question(customer)
      question.post!(question.seller, "It is, and it ships from London.")

      get shop_conversations_path

      assert_select "[data-conversation=?] [data-unread-count=?]", question.id.to_s, "1"
    end

    test "another customer's threads stay off the inbox" do
      visiting_customer_after_sign_in
      listing_question(create_verified_customer)

      get shop_conversations_path

      assert_select "[data-conversation]", false
    end

    test "the thread reads oldest message first and names who wrote each" do
      customer = visiting_customer_after_sign_in
      question = listing_question(customer)
      question.post!(customer, "Is this still available?", at: moment("2026-08-21 09:00:00"))
      question.post!(question.seller, "It is.", at: moment("2026-08-21 10:00:00"))

      get shop_conversation_path(question)

      assert_response :success
      assert_select "h1", text: "“Harbour at Dusk”"
      assert_select "[data-cell=counterpart]", text: /Blue Kiln Studio/
      assert_select "[data-message]", 2
      assert_select "[data-message]:first-of-type", text: /Is this still available\?/
      assert_select "form[action=?][method=post]", shop_conversation_messages_path(question)
    end

    test "opening a thread reads what the other side sent" do
      customer = visiting_customer_after_sign_in
      question = listing_question(customer)
      question.post!(question.seller, "It is.")

      get shop_conversation_path(question)

      assert_equal 0, question.unread_count_for(customer)
    end

    test "the nav counts what is unread across threads" do
      customer = visiting_customer_after_sign_in
      question = listing_question(customer)
      question.post!(question.seller, "Anyone there?")

      get root_path

      assert_select "[data-unread-messages]", text: "1"
    end

    test "a thread the customer is not in is not found" do
      visiting_customer_after_sign_in

      get shop_conversation_path(listing_question(create_verified_customer))

      assert_response :not_found
    end

    test "an id no thread carries is not found" do
      visiting_customer_after_sign_in

      get shop_conversation_path(id: "not-a-number")

      assert_response :not_found
    end

    private

    def visiting_customer_after_sign_in
      sign_in_as_customer(email: "buyer@example.com")

      visiting_customer
    end

    def listing_question(customer, at: Time.current)
      seller = create_seller

      Conversation.open(
        kind: :listing_question, seller: seller, customer: customer,
        subject: create_listing(seller, title: "Harbour at Dusk"), at: at
      )
    end

    def support_thread(customer, at: Time.current)
      Conversation.open(kind: :admin_customer, admin: create_admin, customer: customer, at: at)
    end
  end
end

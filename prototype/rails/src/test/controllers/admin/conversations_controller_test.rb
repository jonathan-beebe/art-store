require "test_helper"

class Admin::ConversationsControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor reaches no inbox and no thread" do
    get admin_conversations_path
    assert_redirected_to admin_login_path

    get admin_conversation_path(id: 1)
    assert_redirected_to admin_login_path
  end

  test "the inbox lists the operator's threads newest first" do
    admin = sign_in_as_admin
    with_seller = Conversation.open(
      kind: :admin_seller, admin: admin, seller: create_seller, at: moment("2026-08-20 09:00:00")
    )
    with_customer = Conversation.open(
      kind: :admin_customer, admin: admin,
      customer: create_verified_customer(name: "Ada Lovelace"), at: moment("2026-08-21 09:00:00")
    )

    get admin_conversations_path

    assert_response :success
    assert_select "[data-conversation]", 2
    assert_select "[data-conversation]:first-of-type", text: /Ada Lovelace/
    assert_select "[data-conversation=?]", with_seller.id.to_s, text: /Blue Kiln Studio/
    assert_select "[data-conversation=?] [data-cell=topic]", with_customer.id.to_s, text: "Art Store support"
  end

  test "an inbox row counts what this side has not read" do
    admin = sign_in_as_admin
    thread = support_thread(admin)
    thread.post!(thread.seller, "My payout is late.")

    get admin_conversations_path

    assert_select "[data-conversation=?] [data-unread-count=?]", thread.id.to_s, "1"
  end

  test "another operator's threads stay off the inbox" do
    sign_in_as_admin
    support_thread(create_admin(email: "other@example.test"))

    get admin_conversations_path

    assert_select "[data-conversation]", false
    assert_select "p", text: "Nothing yet."
  end

  test "the thread reads oldest message first and names who wrote each" do
    admin = sign_in_as_admin(create_admin(name: "Ops desk"))
    thread = support_thread(admin)
    thread.post!(thread.seller, "My payout is late.", at: moment("2026-08-21 09:00:00"))
    thread.post!(admin, "Looking into it.", at: moment("2026-08-21 10:00:00"))

    get admin_conversation_path(thread)

    assert_response :success
    assert_select "h1", text: "Art Store support"
    assert_select "[data-cell=counterpart]", text: /Blue Kiln Studio/
    assert_select "[data-message]", 2
    assert_select "[data-message]:first-of-type", text: /My payout is late\./
    assert_select "[data-message]:last-of-type", text: /Ops desk/
    assert_select "form[action=?][method=post]", admin_conversation_messages_path(thread)
  end

  test "opening a thread reads what the other side sent" do
    admin = sign_in_as_admin
    thread = support_thread(admin)
    thread.post!(thread.seller, "My payout is late.")

    get admin_conversation_path(thread)

    assert_equal 0, thread.unread_count_for(admin)
  end

  test "the nav counts what is unread across threads" do
    admin = sign_in_as_admin
    thread = support_thread(admin)
    thread.post!(thread.seller, "My payout is late.")

    get admin_root_path

    assert_select "[data-unread-messages]", text: "1"
  end

  test "the thread page subscribes to the thread and to the reader's badge" do
    admin = sign_in_as_admin
    thread = support_thread(admin)

    get admin_conversation_path(thread)

    assert_select "turbo-cable-stream-source[signed-stream-name]", 2
    assert_select "script[type=importmap]"
  end

  test "a thread the operator is not in is not found" do
    sign_in_as_admin

    get admin_conversation_path(support_thread(create_admin(email: "other@example.test")))

    assert_response :not_found
  end

  test "an id no thread carries is not found" do
    sign_in_as_admin

    get admin_conversation_path(id: "not-a-number")

    assert_response :not_found
  end

  private

  def support_thread(admin)
    Conversation.open(kind: :admin_seller, admin: admin, seller: create_seller)
  end
end

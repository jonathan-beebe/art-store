require "test_helper"

class Seller::OrderConversationsControllerTest < ActionDispatch::IntegrationTest
  test "the order page opens the thread with the customer about that order" do
    seller = signed_in_seller
    fulfillment = create_fulfillment(seller)

    post seller_order_conversation_path(fulfillment)

    conversation = Conversation.sole
    assert_redirected_to seller_conversation_path(conversation)
    assert_equal fulfillment, conversation.subject
    assert_equal seller, conversation.seller
    assert_equal fulfillment.order.customer, conversation.customer
  end

  test "a second press reaches the thread already open" do
    seller = signed_in_seller
    fulfillment = create_fulfillment(seller)

    post seller_order_conversation_path(fulfillment)
    post seller_order_conversation_path(fulfillment)

    assert_equal 1, Conversation.count
  end

  test "another seller's order opens no thread" do
    signed_in_seller

    post seller_order_conversation_path(create_fulfillment(other_seller))

    assert_response :not_found
    assert_empty Conversation.all
  end

  test "the order page carries the button" do
    seller = signed_in_seller
    fulfillment = create_fulfillment(seller)

    get seller_order_path(fulfillment)

    assert_select "form[action=?][method=post] button",
      seller_order_conversation_path(fulfillment), text: "Message the customer"
  end
end

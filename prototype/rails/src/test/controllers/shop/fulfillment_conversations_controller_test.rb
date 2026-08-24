require "test_helper"

module Shop
  class FulfillmentConversationsControllerTest < ActionDispatch::IntegrationTest
    test "the order page opens the thread with the seller of that fulfillment" do
      order = paid_order
      fulfillment = order.fulfillments.sole

      post shop_fulfillment_conversation_path(order_id: order.id, id: fulfillment.id)

      conversation = Conversation.sole
      assert_redirected_to shop_conversation_path(conversation)
      assert_equal fulfillment, conversation.subject
      assert_equal fulfillment.seller, conversation.seller
      assert_equal order.customer, conversation.customer
    end

    test "a second press reaches the thread already open" do
      order = paid_order
      fulfillment = order.fulfillments.sole

      post shop_fulfillment_conversation_path(order_id: order.id, id: fulfillment.id)
      post shop_fulfillment_conversation_path(order_id: order.id, id: fulfillment.id)

      assert_equal 1, Conversation.count
    end

    test "another customer's order opens no thread" do
      order = paid_order
      fulfillment = order.fulfillments.sole
      end_session
      sign_in_as_customer(email: "stranger@example.com")

      post shop_fulfillment_conversation_path(order_id: order.id, id: fulfillment.id)

      assert_response :not_found
      assert_empty Conversation.all
    end

    test "the order page carries a button for each fulfillment" do
      order = paid_order

      get shop_order_path(order)

      assert_select "form[action=?][method=post] button",
        shop_fulfillment_conversation_path(order_id: order.id, id: order.fulfillments.sole.id),
        text: "Message the seller"
    end

    private

    def paid_order
      sign_in_as_customer(email: "buyer@example.com")
      post shop_add_to_cart_path(slug: create_listing.slug)
      post shop_place_order_path,
        params: { email: "buyer@example.com", card_number: APPROVED_CARD }.merge(shipping_params)

      visiting_customer.orders.order(:id).last
    end
  end
end

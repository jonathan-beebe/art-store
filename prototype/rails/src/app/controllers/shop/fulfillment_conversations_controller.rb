module Shop
  # The thread a customer and a seller keep about one order. An order may span
  # sellers, so the thread hangs off the fulfillment rather than the order.
  class FulfillmentConversationsController < BaseController
    def create
      fulfillment = order_of_customer(params[:order_id]).fulfillments.find(params[:id])
      conversation = Conversation.open(
        kind: :fulfillment, subject: fulfillment,
        seller: fulfillment.seller, customer: current_customer
      )

      redirect_to shop_conversation_path(conversation)
    end
  end
end

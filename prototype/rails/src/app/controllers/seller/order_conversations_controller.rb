# The thread a seller and a customer keep about one order, opened from the
# fulfillment the seller ships.
class Seller::OrderConversationsController < Seller::BaseController
  def create
    fulfillment = current_seller.fulfillments.find(params[:order_id])
    conversation = Conversation.open(
      kind: :fulfillment, subject: fulfillment,
      seller: current_seller, customer: fulfillment.order.customer
    )

    redirect_to seller_conversation_path(conversation)
  end
end

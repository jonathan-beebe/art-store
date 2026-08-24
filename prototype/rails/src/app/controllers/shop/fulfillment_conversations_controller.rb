module Shop
  # The thread a customer and a seller keep about one order. An order may span
  # sellers, so the thread hangs off the fulfillment rather than the order.
  class FulfillmentConversationsController < BaseController
    rate_limit_guard :conversation_open, by: -> { current_participant.id }, only: :create

    def create
      fulfillment = order_of_customer(params[:order_id]).fulfillments.find(params[:id])
      conversation = Conversation.open(
        kind: :fulfillment, subject: fulfillment,
        seller: fulfillment.seller, customer: current_customer
      )

      redirect_to shop_conversation_path(conversation)
    end

    private

    # A tripped `conversation_open` comes back on the order page the "Message
    # the seller" button sits on, the sentence standing in for a field error —
    # there is no body to preserve, since the button carries none.
    def render_too_many_requests(trip)
      @order = order_of_customer(params[:order_id])
      @fulfillments = @order.fulfillments.includes(:seller, :refunds).order(:created_at, :id)
      @items_by_seller = @order.items.group_by(&:seller_id)
      @payment = @order.payments.order(:created_at, :id).last
      @unpaid = @order.unpaid?
      @payable = @order.payable_by?(customer_signed_in?)
      flash.now[:alert] = rate_limit_message(trip)

      render "shop/orders/show", status: :too_many_requests
    end
  end
end

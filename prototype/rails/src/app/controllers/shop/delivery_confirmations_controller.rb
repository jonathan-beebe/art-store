module Shop
  class DeliveryConfirmationsController < BaseController
    def create
      order = order_of_customer(params[:order_id])
      fulfillment = order.fulfillments.find(params[:id])

      raise ActiveRecord::RecordNotFound unless fulfillment.can_transition_to?(:delivered)

      fulfillment.deliver!

      redirect_to shop_order_path(order)
    end
  end
end

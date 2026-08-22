module Shop
  class DeliveryConfirmationsController < BaseController
    def create
      order = order_of_customer(params[:order_id])
      fulfillment = order.fulfillments.find(params[:id])

      unless Domain::Orders::FulfillmentStatus.can_transition?(
        fulfillment.status, Domain::Orders::FulfillmentStatus::DELIVERED
      )
        raise ActiveRecord::RecordNotFound
      end

      Fulfillments::ConfirmDelivered.new.call(fulfillment: fulfillment, now: now)

      redirect_to shop_order_path(order)
    end
  end
end

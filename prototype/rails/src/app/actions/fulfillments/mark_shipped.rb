module Fulfillments
  class MarkShipped
    def initialize(roll_up_order_status: Orders::RollUpOrderStatus.new, notify: Notifications::Notify.new)
      @roll_up_order_status = roll_up_order_status
      @notify = notify
    end

    def call(fulfillment:, carrier:, tracking_number:, now:)
      status = Domain::Orders::FulfillmentStatus.transition(
        fulfillment.status, Domain::Orders::FulfillmentStatus::SHIPPED
      )

      fulfillment.transaction do
        fulfillment.update!(status: status, carrier: carrier, tracking_number: tracking_number, shipped_at: now)
        tell_the_customer(@roll_up_order_status.call(order: fulfillment.order), carrier, tracking_number)
      end

      fulfillment
    end

    private

    def tell_the_customer(order, carrier, tracking_number)
      @notify.call(
        recipient_type: Domain::Notifications::RecipientType::CUSTOMER,
        recipient_id: order.customer_id,
        message: Domain::Notifications::NotificationMessage.order_shipped(order.id, carrier, tracking_number)
      )
    end
  end
end

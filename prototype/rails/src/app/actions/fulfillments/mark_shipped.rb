module Fulfillments
  class MarkShipped
    def initialize(notify: Notifications::Notify.new)
      @notify = notify
    end

    def call(fulfillment:, carrier:, tracking_number:, now:)
      status = Domain::Orders::FulfillmentStatus.transition(
        fulfillment.status, Domain::Orders::FulfillmentStatus::SHIPPED
      )

      fulfillment.transaction do
        fulfillment.update!(status: status, carrier: carrier, tracking_number: tracking_number, shipped_at: now)
        tell_the_customer(fulfillment.order.roll_up_status!, carrier, tracking_number)
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

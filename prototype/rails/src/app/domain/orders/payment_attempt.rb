require_relative "order_status"
require_relative "order_stock"
require_relative "../payments/payment_status"

module Domain
  module Orders
    # What one charge attempt does to an order: the status it lands on, the
    # payments row it writes, and what happens to the stock it claimed.
    PaymentAttempt = Data.define(
      :order_status, :payment_status, :card_last_four, :decline_reason, :stock_change, :finalized_at
    ) do
      def self.from(status:, decision:, now:)
        order_status = OrderStatus.transition(status, OrderStatus.from_card_decision(decision))

        new(
          order_status: order_status,
          payment_status: Payments::PaymentStatus.from_card_decision(decision),
          card_last_four: decision.last_four,
          decline_reason: decision.decline_reason,
          stock_change: OrderStock.change(from: status, to: order_status),
          finalized_at: (now if order_status == OrderStatus::PAID)
        )
      end

      def paid?
        order_status == OrderStatus::PAID
      end

      # A declined charge settles nothing, so the caller writes no ledger
      # entries and sends no notifications.
      def settled(fulfillments)
        paid? ? fulfillments : []
      end
    end
  end
end

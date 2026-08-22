require_relative "order_status"

module Domain
  module Orders
    module OrderPayment
      module_function

      # An order awaits a card for as long as one could still carry it to paid,
      # which is what the storefront asks before it shows a card form.
      def awaits_card?(status)
        OrderStatus.can_transition?(status, OrderStatus::PAID)
      end

      # A guest's order is unpaid before it is chargeable: verifying the address
      # is the step between.
      def unpaid?(status)
        awaits_card?(status) || OrderStatus.can_transition?(status, OrderStatus::AWAITING_PAYMENT)
      end

      def payable?(status, purchaser_verified)
        purchaser_verified && awaits_card?(status)
      end
    end
  end
end

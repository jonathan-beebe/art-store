require_relative "fulfillment_status"
require_relative "../transition_error"

module Domain
  module Orders
    module OrderStatus
      PENDING_VERIFICATION = "pending_verification"
      AWAITING_PAYMENT = "awaiting_payment"
      PAID = "paid"
      PAYMENT_FAILED = "payment_failed"
      PARTIALLY_SHIPPED = "partially_shipped"
      SHIPPED = "shipped"
      DELIVERED = "delivered"
      CANCELLED = "cancelled"

      ALL = [
        PENDING_VERIFICATION, AWAITING_PAYMENT, PAID, PAYMENT_FAILED,
        PARTIALLY_SHIPPED, SHIPPED, DELIVERED, CANCELLED
      ].freeze

      TRANSITIONS = {
        PENDING_VERIFICATION => [AWAITING_PAYMENT, CANCELLED].freeze,
        AWAITING_PAYMENT => [PAID, PAYMENT_FAILED, CANCELLED].freeze,
        # A retry that is declined again leaves the order where it already was.
        PAYMENT_FAILED => [PAID, PAYMENT_FAILED, CANCELLED].freeze,
        PAID => [PARTIALLY_SHIPPED, SHIPPED].freeze,
        PARTIALLY_SHIPPED => [SHIPPED].freeze,
        SHIPPED => [DELIVERED].freeze,
        DELIVERED => [].freeze,
        CANCELLED => [].freeze
      }.freeze

      module_function

      def can_transition?(from, to)
        TRANSITIONS.fetch(from, []).include?(to)
      end

      def transition(from, to)
        return to if can_transition?(from, to)

        raise TransitionError, "An order cannot move from #{from} to #{to}."
      end

      def for_placement(purchaser)
        purchaser.email_verified? ? AWAITING_PAYMENT : PENDING_VERIFICATION
      end

      def after_verification(status)
        can_transition?(status, AWAITING_PAYMENT) ? AWAITING_PAYMENT : status
      end

      def from_card_decision(decision)
        decision.approved? ? PAID : PAYMENT_FAILED
      end

      # An order that spans sellers reads from its fulfillments: a delivered one
      # mixed with an unshipped one is still partially shipped.
      def from_fulfillments(statuses)
        raise ArgumentError, "an order rolls up from at least one fulfillment" if statuses.empty?
        return DELIVERED if statuses.all? { |status| status == FulfillmentStatus::DELIVERED }
        return SHIPPED if statuses.all? { |status| FulfillmentStatus.departed?(status) }
        return PARTIALLY_SHIPPED if statuses.any? { |status| FulfillmentStatus.departed?(status) }

        PAID
      end
    end
  end
end

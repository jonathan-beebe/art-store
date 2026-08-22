require_relative "../transition_error"

module Domain
  module Orders
    module FulfillmentStatus
      AWAITING_SHIPMENT = "awaiting_shipment"
      SHIPPED = "shipped"
      DELIVERED = "delivered"

      ALL = [AWAITING_SHIPMENT, SHIPPED, DELIVERED].freeze

      TRANSITIONS = {
        AWAITING_SHIPMENT => [SHIPPED].freeze,
        SHIPPED => [DELIVERED].freeze,
        DELIVERED => [].freeze
      }.freeze

      DEPARTED = [SHIPPED, DELIVERED].freeze

      module_function

      def can_transition?(from, to)
        TRANSITIONS.fetch(from, []).include?(to)
      end

      def transition(from, to)
        return to if can_transition?(from, to)

        raise TransitionError, "A fulfillment cannot move from #{from} to #{to}."
      end

      def departed?(status)
        DEPARTED.include?(status)
      end
    end
  end
end

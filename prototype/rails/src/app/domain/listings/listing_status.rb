require_relative "../transition_error"

module Domain
  module Listings
    module ListingStatus
      DRAFT = "draft"
      FOR_SALE = "for_sale"
      SOLD = "sold"
      ARCHIVED = "archived"

      ALL = [DRAFT, FOR_SALE, SOLD, ARCHIVED].freeze

      TRANSITIONS = {
        DRAFT => [FOR_SALE, ARCHIVED].freeze,
        FOR_SALE => [SOLD, ARCHIVED].freeze,
        # A declined card hands the stock back, so a sold-out listing returns to
        # the storefront.
        SOLD => [FOR_SALE].freeze,
        ARCHIVED => [].freeze
      }.freeze

      module_function

      def can_transition?(from, to)
        TRANSITIONS.fetch(from, []).include?(to)
      end

      def transition(from, to)
        return to if can_transition?(from, to)

        raise TransitionError, "A listing cannot move from #{from} to #{to}."
      end
    end
  end
end

module Domain
  module Payments
    module PaymentStatus
      APPROVED = "approved"
      DECLINED = "declined"

      ALL = [APPROVED, DECLINED].freeze

      module_function

      def from_card_decision(decision)
        decision.approved? ? APPROVED : DECLINED
      end
    end
  end
end

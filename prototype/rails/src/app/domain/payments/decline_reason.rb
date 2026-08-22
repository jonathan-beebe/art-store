module Domain
  module Payments
    module DeclineReason
      GENERIC_DECLINE = "generic_decline"
      INSUFFICIENT_FUNDS = "insufficient_funds"
      INVALID_CARD_NUMBER = "invalid_card_number"

      ALL = [GENERIC_DECLINE, INSUFFICIENT_FUNDS, INVALID_CARD_NUMBER].freeze

      MESSAGES = {
        GENERIC_DECLINE => "Your card was declined.",
        INSUFFICIENT_FUNDS => "Your card has insufficient funds.",
        INVALID_CARD_NUMBER => "That card number is not valid."
      }.freeze

      module_function

      def message(reason)
        MESSAGES.fetch(reason)
      end
    end
  end
end

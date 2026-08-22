require_relative "card_decision"
require_relative "decline_reason"

module Domain
  module Payments
    # The prototype's stand-in for a card processor: the number decides the
    # answer, and nothing but its last four digits is ever kept.
    module FakeCard
      APPROVED_NUMBER = "4242424242424242"

      DECLINED_NUMBERS = {
        "4000000000000002" => DeclineReason::GENERIC_DECLINE,
        "4000000000009995" => DeclineReason::INSUFFICIENT_FUNDS
      }.freeze

      module_function

      def decide(number)
        digits = number.to_s.gsub(/\D/, "")
        last_four = digits[-4..] || digits
        return CardDecision.approved(last_four) if digits == APPROVED_NUMBER

        CardDecision.declined(last_four, DECLINED_NUMBERS.fetch(digits, DeclineReason::INVALID_CARD_NUMBER))
      end
    end
  end
end

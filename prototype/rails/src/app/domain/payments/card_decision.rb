module Domain
  module Payments
    # What the card processor said about one number.
    CardDecision = Data.define(:last_four, :decline_reason) do
      def self.approved(last_four)
        new(last_four: last_four, decline_reason: nil)
      end

      def self.declined(last_four, decline_reason)
        new(last_four: last_four, decline_reason: decline_reason)
      end

      def approved?
        decline_reason.nil?
      end
    end
  end
end

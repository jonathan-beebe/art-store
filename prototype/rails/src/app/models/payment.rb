class Payment < ApplicationRecord
  belongs_to :order

  enum :status, { approved: "approved", declined: "declined" }
  enum :decline_reason, {
    generic_decline: "generic_decline",
    insufficient_funds: "insufficient_funds",
    invalid_card_number: "invalid_card_number"
  }

  DECLINE_MESSAGES = {
    "generic_decline" => "Your card was declined.",
    "insufficient_funds" => "Your card has insufficient funds.",
    "invalid_card_number" => "That card number is not valid."
  }.freeze

  # What the storefront tells the customer a decline was about.
  def decline_message
    DECLINE_MESSAGES.fetch(decline_reason)
  end
end

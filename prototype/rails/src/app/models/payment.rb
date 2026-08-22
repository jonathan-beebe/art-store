class Payment < ApplicationRecord
  belongs_to :order

  enum :status, Domain::Payments::PaymentStatus::ALL.index_by(&:to_sym)
  enum :decline_reason, Domain::Payments::DeclineReason::ALL.index_by(&:to_sym)
end

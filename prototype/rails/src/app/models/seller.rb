class Seller < ApplicationRecord
  has_many :listings
  has_many :fulfillments
  has_many :ledger_entries
  has_many :payouts
  has_many :notifications

  normalizes :email, with: ->(email) { Domain::Auth::EmailAddress.normalize(email) }

  def escrow_balance
    Domain::Escrow::LedgerBalance.from(ledger_entries.map(&:to_movement))
  end
end

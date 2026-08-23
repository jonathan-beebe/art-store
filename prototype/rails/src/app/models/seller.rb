class Seller < ApplicationRecord
  include EmailAddress

  has_many :listings
  has_many :fulfillments
  has_many :ledger_entries
  has_many :payouts
  has_many :notifications

  # A verified link is the whole of the seller sign-up flow: the first one for
  # an address creates the account.
  def self.claim(email)
    seller = find_or_initialize_by(email: email)
    seller.email_verified_at ||= Time.current
    seller.save!

    seller
  end

  def escrow_balance
    Domain::Escrow::LedgerBalance.from(ledger_entries.map(&:to_movement))
  end
end

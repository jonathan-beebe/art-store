class Seller < ApplicationRecord
  include EmailAddress

  has_many :listings
  has_many :fulfillments
  has_many :ledger_entries
  has_many :payouts
  has_many :notifications, as: :recipient, dependent: :destroy

  # A verified link is the whole of the seller sign-up flow: the first one for
  # an address creates the account.
  def self.claim(email)
    seller = find_or_initialize_by(email: email)
    seller.email_verified_at ||= Time.current
    seller.save!

    seller
  end

  # A seller signs up with an address and names their shop later, so the
  # storefront falls back to the part of the address in front of the host.
  def display_name
    shop_name.to_s.strip.presence || email.to_s.split("@").first.to_s
  end

  def escrow_balance
    ledger_entries.balance
  end

  # Every listing status in lifecycle order, so the dashboard keeps its tiles
  # in place on a day nothing sold.
  def listing_status_counts
    counts = listings.group(:status).count

    Listing.statuses.keys.map { |status| [status, counts.fetch(status, 0)] }
  end
end

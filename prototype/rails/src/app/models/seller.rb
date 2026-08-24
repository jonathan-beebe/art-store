class Seller < ApplicationRecord
  prefixed_id :sel

  include EmailAddress
  include Messaging

  # One line of the sellers directory: a seller beside the counts and the
  # balance the table shows for them.
  Row = Data.define(:seller, :listing_count, :fulfillment_count, :balance) do
    delegate :id, :email, :display_name, to: :seller
  end

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

  # Every seller with the counts and the balance the directory shows. The
  # balance folds one ledger read for the whole table, so the page costs the
  # same for one seller as for a hundred.
  def self.directory
    balances = LedgerEntry.balances_by_seller
    listing_counts = Listing.group(:seller_id).count
    fulfillment_counts = Fulfillment.group(:seller_id).count

    order(:created_at, :id).map do |seller|
      Row.new(
        seller: seller,
        listing_count: listing_counts.fetch(seller.id, 0),
        fulfillment_count: fulfillment_counts.fetch(seller.id, 0),
        balance: balances.fetch(seller.id, LedgerEntry::Balance.zero)
      )
    end
  end

  def escrow_balance
    ledger_entries.balance
  end

  # Every listing status in lifecycle order, so the dashboard keeps its tiles
  # in place on a day nothing sold.
  def listing_status_counts
    counts = listings.group(:status).count

    Listing.statuses.keys.map { |status| [ status, counts.fetch(status, 0) ] }
  end
end

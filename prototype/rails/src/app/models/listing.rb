class Listing < ApplicationRecord
  belongs_to :seller
  has_many :listing_events, dependent: :destroy
  has_many :favorites, dependent: :destroy
  has_many :cart_items, dependent: :destroy
  has_many :order_items, dependent: :restrict_with_error
  has_one_attached :image

  enum :status, Domain::Listings::ListingStatus::ALL.index_by(&:to_sym)

  def price
    Domain::Money.from_cents(price_cents)
  end
end

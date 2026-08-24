class ListingEvent < ApplicationRecord
  prefixed_id :lev

  # What an event log adds up to. An unfavorite is recorded as its own event
  # and leaves the favorites count where it was.
  Totals = Data.define(:views, :favorites, :cart_adds) do
    def self.from(counts_by_event_type)
      new(
        views: counts_by_event_type.fetch("view", 0),
        favorites: counts_by_event_type.fetch("favorite", 0),
        cart_adds: counts_by_event_type.fetch("cart_add", 0)
      )
    end

    def total
      views + favorites + cart_adds
    end
  end

  Day = Data.define(:date, :totals) do
    def label
      date.strftime("%b %-d")
    end
  end

  belongs_to :listing
  belongs_to :customer, optional: true

  enum :event_type, { view: "view", favorite: "favorite", unfavorite: "unfavorite", cart_add: "cart_add" }

  # Every page load would otherwise be a row, and a seller's activity numbers
  # only need to know that someone looked, not how many times they
  # refreshed — a favorite or a cart add is a deliberate act and is recorded
  # every time.
  ONCE_PER_HOUR = %w[view].freeze

  def self.recorded_once_per_hour?(event_type)
    ONCE_PER_HOUR.include?(event_type.to_s)
  end

  # The window a repeated event collapses into: the UTC hour containing `at`.
  def self.view_window_start(at)
    at.utc.beginning_of_hour
  end

  # Totals for a whole table of listings, so the seller's index costs one query
  # rather than one per row.
  def self.totals_by_listing(listings)
    counts = where(listing_id: listings.map(&:id)).group(:listing_id, :event_type).count

    listings.index_with do |listing|
      Totals.from(counts.select { |(id, _)| id == listing.id }.transform_keys(&:last))
    end
  end
end

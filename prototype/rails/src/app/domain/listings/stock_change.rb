module Domain
  module Listings
    # What an order does to the stock a listing holds.
    module StockChange
      TAKE = "take"
      RESTORE = "restore"
      KEEP = "keep"

      ALL = [TAKE, RESTORE, KEEP].freeze
    end
  end
end

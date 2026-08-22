module Domain
  module Listings
    module ListingEventType
      VIEW = "view"
      FAVORITE = "favorite"
      UNFAVORITE = "unfavorite"
      CART_ADD = "cart_add"

      ALL = [VIEW, FAVORITE, UNFAVORITE, CART_ADD].freeze
    end
  end
end

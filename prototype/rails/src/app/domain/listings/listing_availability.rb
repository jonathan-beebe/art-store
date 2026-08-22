require_relative "listing_status"

module Domain
  module Listings
    module ListingAvailability
      # A sold listing keeps its page so the links a buyer already followed
      # still lead somewhere; a draft or archived one was never public.
      ON_STOREFRONT = [ListingStatus::FOR_SALE, ListingStatus::SOLD].freeze

      module_function

      def on_storefront?(status)
        ON_STOREFRONT.include?(status)
      end

      def purchasable?(status, quantity)
        status == ListingStatus::FOR_SALE && quantity.positive?
      end
    end
  end
end

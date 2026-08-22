require_relative "listing_status"
require_relative "stock_change"

module Domain
  module Listings
    # What a listing has left once an order takes stock or hands it back.
    ListingStock = Data.define(:quantity, :status) do
      def self.after(change, quantity:, status:, items:)
        case change
        when StockChange::TAKE then after_sale(quantity: quantity, status: status, sold: items)
        when StockChange::RESTORE then after_restock(quantity: quantity, status: status, restored: items)
        when StockChange::KEEP then new(quantity: quantity, status: status)
        else raise ArgumentError, "unknown stock change: #{change.inspect}"
        end
      end

      def self.after_sale(quantity:, status:, sold:)
        reject_an_empty_change(sold)
        raise ArgumentError, "a listing that is #{status} cannot be sold" unless status == ListingStatus::FOR_SALE
        raise ArgumentError, "a listing with #{quantity} left cannot sell #{sold}" if sold > quantity

        remaining = quantity - sold
        new(quantity: remaining, status: remaining.zero? ? ListingStatus.transition(status, ListingStatus::SOLD) : status)
      end

      def self.after_restock(quantity:, status:, restored:)
        reject_an_empty_change(restored)

        new(
          quantity: quantity + restored,
          status: status == ListingStatus::SOLD ? ListingStatus.transition(status, ListingStatus::FOR_SALE) : status
        )
      end

      def self.reject_an_empty_change(items)
        raise ArgumentError, "a stock change covers at least one item, got #{items}" if items < 1
      end
      private_class_method :reject_an_empty_change
    end
  end
end

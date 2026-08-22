require "test_helper"

module Domain
  module Listings
    class ListingStockTest < ActiveSupport::TestCase
      test "a sale takes the quantity it asks for" do
        stock = ListingStock.after_sale(quantity: 3, status: ListingStatus::FOR_SALE, sold: 2)

        assert_equal 1, stock.quantity
        assert_equal ListingStatus::FOR_SALE, stock.status
      end

      test "the last of a listing marks it sold" do
        stock = ListingStock.after_sale(quantity: 1, status: ListingStatus::FOR_SALE, sold: 1)

        assert_equal 0, stock.quantity
        assert_equal ListingStatus::SOLD, stock.status
      end

      test "a sale refuses to take more than is left" do
        assert_raises(ArgumentError) { ListingStock.after_sale(quantity: 1, status: ListingStatus::FOR_SALE, sold: 2) }
      end

      test "a sale refuses a listing that is not for sale" do
        assert_raises(ArgumentError) { ListingStock.after_sale(quantity: 1, status: ListingStatus::DRAFT, sold: 1) }
      end

      test "a sale covers at least one item" do
        assert_raises(ArgumentError) { ListingStock.after_sale(quantity: 3, status: ListingStatus::FOR_SALE, sold: 0) }
      end

      test "a restock puts a sold listing back on the storefront" do
        stock = ListingStock.after_restock(quantity: 0, status: ListingStatus::SOLD, restored: 1)

        assert_equal 1, stock.quantity
        assert_equal ListingStatus::FOR_SALE, stock.status
      end

      test "a restock leaves a listing that is still for sale alone" do
        stock = ListingStock.after_restock(quantity: 2, status: ListingStatus::FOR_SALE, restored: 1)

        assert_equal 3, stock.quantity
        assert_equal ListingStatus::FOR_SALE, stock.status
      end

      test "a restock covers at least one item" do
        assert_raises(ArgumentError) { ListingStock.after_restock(quantity: 0, status: ListingStatus::SOLD, restored: 0) }
      end

      test "take sells the items" do
        stock = ListingStock.after(StockChange::TAKE, quantity: 2, status: ListingStatus::FOR_SALE, items: 1)

        assert_equal 1, stock.quantity
      end

      test "restore hands the items back" do
        stock = ListingStock.after(StockChange::RESTORE, quantity: 0, status: ListingStatus::SOLD, items: 1)

        assert_equal 1, stock.quantity
        assert_equal ListingStatus::FOR_SALE, stock.status
      end

      test "keep leaves the listing as it is" do
        stock = ListingStock.after(StockChange::KEEP, quantity: 2, status: ListingStatus::FOR_SALE, items: 1)

        assert_equal 2, stock.quantity
        assert_equal ListingStatus::FOR_SALE, stock.status
      end

      test "it refuses a change it does not know" do
        assert_raises(ArgumentError) do
          ListingStock.after("reserve", quantity: 2, status: ListingStatus::FOR_SALE, items: 1)
        end
      end
    end
  end
end

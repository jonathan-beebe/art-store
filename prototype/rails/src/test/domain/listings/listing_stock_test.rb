require "test_helper"

module Domain
  module Listings
    class ListingStockTest < ActiveSupport::TestCase
      test "a sale takes the quantity it asks for" do
        stock = ListingStock.after_sale(quantity: 3, status: "for_sale", sold: 2)

        assert_equal 1, stock.quantity
        assert_equal "for_sale", stock.status
      end

      test "the last of a listing marks it sold" do
        stock = ListingStock.after_sale(quantity: 1, status: "for_sale", sold: 1)

        assert_equal 0, stock.quantity
        assert_equal "sold", stock.status
      end

      test "a sale refuses to take more than is left" do
        assert_raises(ArgumentError) { ListingStock.after_sale(quantity: 1, status: "for_sale", sold: 2) }
      end

      test "a sale refuses a listing that is not for sale" do
        assert_raises(ArgumentError) { ListingStock.after_sale(quantity: 1, status: "draft", sold: 1) }
      end

      test "a sale covers at least one item" do
        assert_raises(ArgumentError) { ListingStock.after_sale(quantity: 3, status: "for_sale", sold: 0) }
      end

      test "a restock puts a sold listing back on the storefront" do
        stock = ListingStock.after_restock(quantity: 0, status: "sold", restored: 1)

        assert_equal 1, stock.quantity
        assert_equal "for_sale", stock.status
      end

      test "a restock leaves a listing that is still for sale alone" do
        stock = ListingStock.after_restock(quantity: 2, status: "for_sale", restored: 1)

        assert_equal 3, stock.quantity
        assert_equal "for_sale", stock.status
      end

      test "a restock covers at least one item" do
        assert_raises(ArgumentError) { ListingStock.after_restock(quantity: 0, status: "sold", restored: 0) }
      end

      test "take sells the items" do
        stock = ListingStock.after(StockChange::TAKE, quantity: 2, status: "for_sale", items: 1)

        assert_equal 1, stock.quantity
      end

      test "restore hands the items back" do
        stock = ListingStock.after(StockChange::RESTORE, quantity: 0, status: "sold", items: 1)

        assert_equal 1, stock.quantity
        assert_equal "for_sale", stock.status
      end

      test "keep leaves the listing as it is" do
        stock = ListingStock.after(StockChange::KEEP, quantity: 2, status: "for_sale", items: 1)

        assert_equal 2, stock.quantity
        assert_equal "for_sale", stock.status
      end

      test "it refuses a change it does not know" do
        assert_raises(ArgumentError) do
          ListingStock.after("reserve", quantity: 2, status: "for_sale", items: 1)
        end
      end
    end
  end
end

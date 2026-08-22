require "test_helper"

module Domain
  module Listings
    class ListingStockTest < ActiveSupport::TestCase
      def test_a_sale_takes_the_quantity_it_asks_for
        stock = ListingStock.after_sale(quantity: 3, status: ListingStatus::FOR_SALE, sold: 2)

        assert_equal 1, stock.quantity
        assert_equal ListingStatus::FOR_SALE, stock.status
      end

      def test_the_last_of_a_listing_marks_it_sold
        stock = ListingStock.after_sale(quantity: 1, status: ListingStatus::FOR_SALE, sold: 1)

        assert_equal 0, stock.quantity
        assert_equal ListingStatus::SOLD, stock.status
      end

      def test_a_sale_refuses_to_take_more_than_is_left
        assert_raises(ArgumentError) { ListingStock.after_sale(quantity: 1, status: ListingStatus::FOR_SALE, sold: 2) }
      end

      def test_a_sale_refuses_a_listing_that_is_not_for_sale
        assert_raises(ArgumentError) { ListingStock.after_sale(quantity: 1, status: ListingStatus::DRAFT, sold: 1) }
      end

      def test_a_sale_covers_at_least_one_item
        assert_raises(ArgumentError) { ListingStock.after_sale(quantity: 3, status: ListingStatus::FOR_SALE, sold: 0) }
      end

      def test_a_restock_puts_a_sold_listing_back_on_the_storefront
        stock = ListingStock.after_restock(quantity: 0, status: ListingStatus::SOLD, restored: 1)

        assert_equal 1, stock.quantity
        assert_equal ListingStatus::FOR_SALE, stock.status
      end

      def test_a_restock_leaves_a_listing_that_is_still_for_sale_alone
        stock = ListingStock.after_restock(quantity: 2, status: ListingStatus::FOR_SALE, restored: 1)

        assert_equal 3, stock.quantity
        assert_equal ListingStatus::FOR_SALE, stock.status
      end

      def test_a_restock_covers_at_least_one_item
        assert_raises(ArgumentError) { ListingStock.after_restock(quantity: 0, status: ListingStatus::SOLD, restored: 0) }
      end

      def test_take_sells_the_items
        stock = ListingStock.after(StockChange::TAKE, quantity: 2, status: ListingStatus::FOR_SALE, items: 1)

        assert_equal 1, stock.quantity
      end

      def test_restore_hands_the_items_back
        stock = ListingStock.after(StockChange::RESTORE, quantity: 0, status: ListingStatus::SOLD, items: 1)

        assert_equal 1, stock.quantity
        assert_equal ListingStatus::FOR_SALE, stock.status
      end

      def test_keep_leaves_the_listing_as_it_is
        stock = ListingStock.after(StockChange::KEEP, quantity: 2, status: ListingStatus::FOR_SALE, items: 1)

        assert_equal 2, stock.quantity
        assert_equal ListingStatus::FOR_SALE, stock.status
      end

      def test_it_refuses_a_change_it_does_not_know
        assert_raises(ArgumentError) do
          ListingStock.after("reserve", quantity: 2, status: ListingStatus::FOR_SALE, items: 1)
        end
      end
    end
  end
end

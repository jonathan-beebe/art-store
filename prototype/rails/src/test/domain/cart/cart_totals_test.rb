require "test_helper"

module Domain
  module Cart
    class CartTotalsTest < ActiveSupport::TestCase
      def test_it_counts_every_item
        assert_equal 3, CartTotals.from([line(1, 4500, 2), line(1, 1000, 1)]).item_count
      end

      def test_it_adds_every_line
        assert_equal 10_000, CartTotals.from([line(1, 4500, 2), line(2, 1000, 1)]).subtotal.cents
      end

      def test_it_splits_the_subtotal_by_seller
        totals = CartTotals.from([line(2, 1000, 1), line(1, 4500, 2), line(1, 500, 1)])

        assert_equal({ 1 => 9500, 2 => 1000 }, totals.subtotals_by_seller.transform_values(&:cents))
      end

      def test_it_orders_the_sellers_by_id
        totals = CartTotals.from([line(9, 1000, 1), line(3, 1000, 1)])

        assert_equal [3, 9], totals.subtotals_by_seller.keys
      end

      def test_an_empty_cart_totals_nothing
        totals = CartTotals.from([])

        assert_equal 0, totals.item_count
        assert_equal 0, totals.subtotal.cents
        assert_empty totals.subtotals_by_seller
      end

      def test_checkout_refuses_an_empty_cart
        assert_raises(ArgumentError) { CartTotals.for_checkout([]) }
      end

      def test_checkout_totals_a_cart_that_has_something_in_it
        assert_equal 4500, CartTotals.for_checkout([line(1, 4500, 1)]).subtotal.cents
      end

      private

      def line(seller_id, unit_price_cents, quantity)
        CartLine.new(seller_id: seller_id, unit_price: Money.from_cents(unit_price_cents), quantity: quantity)
      end
    end
  end
end

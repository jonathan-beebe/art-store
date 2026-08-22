require "test_helper"

module Domain
  module Orders
    class OrderStockTest < ActiveSupport::TestCase
      def test_an_order_awaiting_payment_holds_its_stock
        assert OrderStock.holds?(OrderStatus::AWAITING_PAYMENT)
      end

      def test_a_failed_payment_holds_nothing
        refute OrderStock.holds?(OrderStatus::PAYMENT_FAILED)
      end

      def test_a_cancelled_order_holds_nothing
        refute OrderStock.holds?(OrderStatus::CANCELLED)
      end

      def test_a_declined_card_hands_the_stock_back
        change = OrderStock.change(from: OrderStatus::AWAITING_PAYMENT, to: OrderStatus::PAYMENT_FAILED)

        assert_equal Listings::StockChange::RESTORE, change
      end

      def test_a_retry_claims_the_stock_again
        change = OrderStock.change(from: OrderStatus::PAYMENT_FAILED, to: OrderStatus::PAID)

        assert_equal Listings::StockChange::TAKE, change
      end

      def test_a_first_payment_leaves_the_stock_placement_already_took
        change = OrderStock.change(from: OrderStatus::AWAITING_PAYMENT, to: OrderStatus::PAID)

        assert_equal Listings::StockChange::KEEP, change
      end

      def test_a_retry_that_is_declined_again_changes_nothing
        change = OrderStock.change(from: OrderStatus::PAYMENT_FAILED, to: OrderStatus::PAYMENT_FAILED)

        assert_equal Listings::StockChange::KEEP, change
      end
    end
  end
end

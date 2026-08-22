require "test_helper"

module Domain
  module Orders
    class OrderStockTest < ActiveSupport::TestCase
      test "an order awaiting payment holds its stock" do
        assert OrderStock.holds?(OrderStatus::AWAITING_PAYMENT)
      end

      test "a failed payment holds nothing" do
        refute OrderStock.holds?(OrderStatus::PAYMENT_FAILED)
      end

      test "a cancelled order holds nothing" do
        refute OrderStock.holds?(OrderStatus::CANCELLED)
      end

      test "a declined card hands the stock back" do
        change = OrderStock.change(from: OrderStatus::AWAITING_PAYMENT, to: OrderStatus::PAYMENT_FAILED)

        assert_equal Listings::StockChange::RESTORE, change
      end

      test "a retry claims the stock again" do
        change = OrderStock.change(from: OrderStatus::PAYMENT_FAILED, to: OrderStatus::PAID)

        assert_equal Listings::StockChange::TAKE, change
      end

      test "a first payment leaves the stock placement already took" do
        change = OrderStock.change(from: OrderStatus::AWAITING_PAYMENT, to: OrderStatus::PAID)

        assert_equal Listings::StockChange::KEEP, change
      end

      test "a retry that is declined again changes nothing" do
        change = OrderStock.change(from: OrderStatus::PAYMENT_FAILED, to: OrderStatus::PAYMENT_FAILED)

        assert_equal Listings::StockChange::KEEP, change
      end
    end
  end
end

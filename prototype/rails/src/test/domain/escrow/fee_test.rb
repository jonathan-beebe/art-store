require "test_helper"

module Domain
  module Escrow
    class FeeTest < ActiveSupport::TestCase
      test "the platform takes a tenth" do
        assert_equal 4500, Fee.platform(Money.from_cents(45_000)).cents
      end

      test "the seller keeps the rest" do
        assert_equal 40_500, Fee.net(Money.from_cents(45_000)).cents
      end

      test "the fee and the net add back up" do
        subtotal = Money.from_cents(4999)

        assert_equal subtotal.cents, Fee.platform(subtotal).cents + Fee.net(subtotal).cents
      end

      test "half a cent rounds away from zero" do
        assert_equal 5, Fee.platform(Money.from_cents(45)).cents
      end

      test "nothing owes nothing" do
        assert_equal 0, Fee.platform(Money.from_cents(0)).cents
      end
    end
  end
end

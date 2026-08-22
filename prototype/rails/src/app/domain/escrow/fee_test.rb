# Runs without Rails: ruby -Iapp app/domain/escrow/fee_test.rb
require "minitest/autorun"
require_relative "fee"

module Domain
  module Escrow
    class FeeTest < Minitest::Test
      def test_the_platform_takes_a_tenth
        assert_equal 4500, Fee.platform(Money.from_cents(45_000)).cents
      end

      def test_the_seller_keeps_the_rest
        assert_equal 40_500, Fee.net(Money.from_cents(45_000)).cents
      end

      def test_the_fee_and_the_net_add_back_up
        subtotal = Money.from_cents(4999)

        assert_equal subtotal.cents, Fee.platform(subtotal).cents + Fee.net(subtotal).cents
      end

      def test_half_a_cent_rounds_away_from_zero
        assert_equal 5, Fee.platform(Money.from_cents(45)).cents
      end

      def test_nothing_owes_nothing
        assert_equal 0, Fee.platform(Money.from_cents(0)).cents
      end
    end
  end
end

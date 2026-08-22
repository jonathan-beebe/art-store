# Runs without Rails: ruby -Iapp app/domain/escrow/ledger_movement_test.rb
require "minitest/autorun"
require_relative "ledger_movement"

module Domain
  module Escrow
    class LedgerMovementTest < Minitest::Test
      def test_a_hold_adds_the_net_to_escrow
        movement = LedgerMovement.hold(Money.from_cents(40_500))

        assert_equal LedgerEntryType::HELD, movement.entry_type
        assert_equal 40_500, movement.amount.cents
      end

      def test_a_release_adds_the_net_to_what_is_available
        movement = LedgerMovement.release(Money.from_cents(40_500))

        assert_equal LedgerEntryType::RELEASED, movement.entry_type
        assert_equal 40_500, movement.amount.cents
      end

      def test_a_payout_leaves_the_ledger_so_it_is_negative
        movement = LedgerMovement.payout(Money.from_cents(40_500))

        assert_equal LedgerEntryType::PAID_OUT, movement.entry_type
        assert_equal(-40_500, movement.amount.cents)
      end
    end
  end
end

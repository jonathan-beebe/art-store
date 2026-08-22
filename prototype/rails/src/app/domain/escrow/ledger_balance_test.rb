# Runs without Rails: ruby -Iapp app/domain/escrow/ledger_balance_test.rb
require "minitest/autorun"
require_relative "ledger_balance"
require_relative "ledger_movement"

module Domain
  module Escrow
    class LedgerBalanceTest < Minitest::Test
      def test_an_empty_ledger_owes_nothing
        balance = LedgerBalance.from([])

        assert_equal 0, balance.held.cents
        assert_equal 0, balance.available.cents
        assert_equal 0, balance.paid_out.cents
        refute_predicate balance, :payable?
      end

      def test_a_hold_waits_on_delivery
        balance = LedgerBalance.from([LedgerMovement.hold(Money.from_cents(40_500))])

        assert_equal 40_500, balance.held.cents
        assert_equal 0, balance.available.cents
        refute_predicate balance, :payable?
      end

      def test_a_release_moves_the_hold_to_available
        balance = LedgerBalance.from(hold_and_release(40_500))

        assert_equal 0, balance.held.cents
        assert_equal 40_500, balance.available.cents
        assert_predicate balance, :payable?
      end

      def test_a_payout_empties_what_was_available
        movements = hold_and_release(40_500) + [LedgerMovement.payout(Money.from_cents(40_500))]
        balance = LedgerBalance.from(movements)

        assert_equal 0, balance.available.cents
        assert_equal 40_500, balance.paid_out.cents
        refute_predicate balance, :payable?
      end

      def test_it_folds_a_ledger_that_holds_and_releases_more_than_once
        movements = hold_and_release(40_500) + [LedgerMovement.hold(Money.from_cents(9000))]
        balance = LedgerBalance.from(movements)

        assert_equal 9000, balance.held.cents
        assert_equal 40_500, balance.available.cents
      end

      private

      def hold_and_release(cents)
        [LedgerMovement.hold(Money.from_cents(cents)), LedgerMovement.release(Money.from_cents(cents))]
      end
    end
  end
end

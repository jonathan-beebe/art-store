require "test_helper"

module Domain
  module Escrow
    class LedgerBalanceTest < ActiveSupport::TestCase
      test "an empty ledger owes nothing" do
        balance = LedgerBalance.from([])

        assert_equal 0, balance.held.cents
        assert_equal 0, balance.available.cents
        assert_equal 0, balance.paid_out.cents
        refute_predicate balance, :payable?
      end

      test "a hold waits on delivery" do
        balance = LedgerBalance.from([LedgerMovement.hold(Money.from_cents(40_500))])

        assert_equal 40_500, balance.held.cents
        assert_equal 0, balance.available.cents
        refute_predicate balance, :payable?
      end

      test "a release moves the hold to available" do
        balance = LedgerBalance.from(hold_and_release(40_500))

        assert_equal 0, balance.held.cents
        assert_equal 40_500, balance.available.cents
        assert_predicate balance, :payable?
      end

      test "a payout empties what was available" do
        movements = hold_and_release(40_500) + [LedgerMovement.payout(Money.from_cents(40_500))]
        balance = LedgerBalance.from(movements)

        assert_equal 0, balance.available.cents
        assert_equal 40_500, balance.paid_out.cents
        refute_predicate balance, :payable?
      end

      test "it folds a ledger that holds and releases more than once" do
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

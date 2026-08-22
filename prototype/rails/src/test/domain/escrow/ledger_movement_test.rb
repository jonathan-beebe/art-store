require "test_helper"

module Domain
  module Escrow
    class LedgerMovementTest < ActiveSupport::TestCase
      test "a hold adds the net to escrow" do
        movement = LedgerMovement.hold(Money.from_cents(40_500))

        assert_equal LedgerEntryType::HELD, movement.entry_type
        assert_equal 40_500, movement.amount.cents
      end

      test "a release adds the net to what is available" do
        movement = LedgerMovement.release(Money.from_cents(40_500))

        assert_equal LedgerEntryType::RELEASED, movement.entry_type
        assert_equal 40_500, movement.amount.cents
      end

      test "a payout leaves the ledger so it is negative" do
        movement = LedgerMovement.payout(Money.from_cents(40_500))

        assert_equal LedgerEntryType::PAID_OUT, movement.entry_type
        assert_equal(-40_500, movement.amount.cents)
      end
    end
  end
end

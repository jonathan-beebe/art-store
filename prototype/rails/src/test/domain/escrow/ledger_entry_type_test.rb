require "test_helper"

module Domain
  module Escrow
    class LedgerEntryTypeTest < ActiveSupport::TestCase
      test "all names every step through escrow" do
        assert_equal %w[held released paid_out], LedgerEntryType::ALL
      end
    end
  end
end

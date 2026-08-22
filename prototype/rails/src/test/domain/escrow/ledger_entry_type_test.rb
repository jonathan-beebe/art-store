require "test_helper"

module Domain
  module Escrow
    class LedgerEntryTypeTest < ActiveSupport::TestCase
      def test_all_names_every_step_through_escrow
        assert_equal %w[held released paid_out], LedgerEntryType::ALL
      end
    end
  end
end

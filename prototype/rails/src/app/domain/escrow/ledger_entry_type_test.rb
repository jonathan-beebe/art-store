# Runs without Rails: ruby -Iapp app/domain/escrow/ledger_entry_type_test.rb
require "minitest/autorun"
require_relative "ledger_entry_type"

module Domain
  module Escrow
    class LedgerEntryTypeTest < Minitest::Test
      def test_all_names_every_step_through_escrow
        assert_equal %w[held released paid_out], LedgerEntryType::ALL
      end
    end
  end
end

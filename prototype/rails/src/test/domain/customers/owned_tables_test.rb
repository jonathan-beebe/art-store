require "test_helper"

module Domain
  module Customers
    class OwnedTablesTest < ActiveSupport::TestCase
      test "it covers every table a merge re points" do
        assert_equal %w[favorites carts orders listing_events notifications],
          OwnedTables::ALL.keys
      end

      test "every table names the column holding the customer" do
        assert_equal ["customer_id"], OwnedTables::ALL.values.uniq
      end

      test "the list cannot be edited by a caller" do
        assert OwnedTables::ALL.frozen?
      end
    end
  end
end

require "test_helper"

module Domain
  module Customers
    class OwnedTablesTest < ActiveSupport::TestCase
      def test_it_covers_every_table_a_merge_re_points
        assert_equal %w[favorites carts orders listing_events notifications],
          OwnedTables::ALL.keys
      end

      def test_every_table_names_the_column_holding_the_customer
        assert_equal ["customer_id"], OwnedTables::ALL.values.uniq
      end

      def test_the_list_cannot_be_edited_by_a_caller
        assert OwnedTables::ALL.frozen?
      end
    end
  end
end

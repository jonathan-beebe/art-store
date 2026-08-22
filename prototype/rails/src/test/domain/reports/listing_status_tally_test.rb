require "test_helper"

module Domain
  module Reports
    class ListingStatusTallyTest < ActiveSupport::TestCase
      def test_it_returns_every_status_in_lifecycle_order
        tally = ListingStatusTally.from({})

        assert_equal %w[draft for_sale sold archived], tally.map(&:status)
      end

      def test_it_reads_the_count_recorded_for_a_status
        tally = ListingStatusTally.from({ "for_sale" => 3 })

        assert_equal 3, tally[1].count
      end

      def test_it_counts_a_status_with_no_listings_as_zero
        tally = ListingStatusTally.from({ "for_sale" => 3 })

        assert_equal 0, tally[0].count
      end

      def test_it_totals_every_status
        tally = ListingStatusTally.from({ "for_sale" => 3, "sold" => 2 })

        assert_equal 5, ListingStatusTally.total(tally)
      end

      def test_a_seller_with_no_listings_totals_zero
        assert_equal 0, ListingStatusTally.total(ListingStatusTally.from({}))
      end
    end
  end
end

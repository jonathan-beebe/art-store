require "test_helper"

module Domain
  module Reports
    class ListingStatusTallyTest < ActiveSupport::TestCase
      test "it returns every status in lifecycle order" do
        tally = ListingStatusTally.from({})

        assert_equal %w[draft for_sale sold archived], tally.map(&:status)
      end

      test "it reads the count recorded for a status" do
        tally = ListingStatusTally.from({ "for_sale" => 3 })

        assert_equal 3, tally[1].count
      end

      test "it counts a status with no listings as zero" do
        tally = ListingStatusTally.from({ "for_sale" => 3 })

        assert_equal 0, tally[0].count
      end

      test "it totals every status" do
        tally = ListingStatusTally.from({ "for_sale" => 3, "sold" => 2 })

        assert_equal 5, ListingStatusTally.total(tally)
      end

      test "a seller with no listings totals zero" do
        assert_equal 0, ListingStatusTally.total(ListingStatusTally.from({}))
      end
    end
  end
end

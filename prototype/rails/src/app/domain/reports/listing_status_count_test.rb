# Runs without Rails: ruby -Iapp app/domain/reports/listing_status_count_test.rb
require "minitest/autorun"
require_relative "listing_status_count"

module Domain
  module Reports
    class ListingStatusCountTest < Minitest::Test
      def test_it_labels_its_status_for_a_dashboard_tile
        assert_equal "For sale", ListingStatusCount.new(status: "for_sale", count: 3).label
      end
    end
  end
end

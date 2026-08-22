require "test_helper"

module Domain
  module Reports
    class ListingStatusCountTest < ActiveSupport::TestCase
      def test_it_labels_its_status_for_a_dashboard_tile
        assert_equal "For sale", ListingStatusCount.new(status: "for_sale", count: 3).label
      end
    end
  end
end

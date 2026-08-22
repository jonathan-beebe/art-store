require "test_helper"

module Domain
  module Reports
    class ListingStatusCountTest < ActiveSupport::TestCase
      test "it labels its status for a dashboard tile" do
        assert_equal "For sale", ListingStatusCount.new(status: "for_sale", count: 3).label
      end
    end
  end
end

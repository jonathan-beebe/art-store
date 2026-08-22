require "test_helper"

module Domain
  module Listings
    class ListingEventTypeTest < ActiveSupport::TestCase
      test "all names every event a listing records" do
        assert_equal %w[view favorite unfavorite cart_add], ListingEventType::ALL
      end
    end
  end
end

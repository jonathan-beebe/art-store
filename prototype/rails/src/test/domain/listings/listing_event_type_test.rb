require "test_helper"

module Domain
  module Listings
    class ListingEventTypeTest < ActiveSupport::TestCase
      def test_all_names_every_event_a_listing_records
        assert_equal %w[view favorite unfavorite cart_add], ListingEventType::ALL
      end
    end
  end
end

# Runs without Rails: ruby -Iapp app/domain/listings/listing_event_type_test.rb
require "minitest/autorun"
require_relative "listing_event_type"

module Domain
  module Listings
    class ListingEventTypeTest < Minitest::Test
      def test_all_names_every_event_a_listing_records
        assert_equal %w[view favorite unfavorite cart_add], ListingEventType::ALL
      end
    end
  end
end

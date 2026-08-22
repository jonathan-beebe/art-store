# Runs without Rails: ruby -Iapp app/domain/listings/listing_slug_test.rb
require "minitest/autorun"
require_relative "listing_slug"

module Domain
  module Listings
    class ListingSlugTest < Minitest::Test
      def test_it_slugs_the_title
        assert_equal "harbour-at-dusk", ListingSlug.first_free("Harbour at Dusk", [])
      end

      def test_it_drops_punctuation_and_edge_separators
        assert_equal "study-no-4", ListingSlug.base("  Study, No. 4!  ")
      end

      def test_it_numbers_a_slug_another_listing_already_holds
        assert_equal "harbour-at-dusk-2", ListingSlug.first_free("Harbour at Dusk", ["harbour-at-dusk"])
      end

      def test_it_keeps_counting_past_a_numbered_slug
        taken = ["harbour-at-dusk", "harbour-at-dusk-2", "harbour-at-dusk-3"]

        assert_equal "harbour-at-dusk-4", ListingSlug.first_free("Harbour at Dusk", taken)
      end

      def test_it_ignores_slugs_another_title_holds
        assert_equal "harbour-at-dusk", ListingSlug.first_free("Harbour at Dusk", ["morning-tide"])
      end

      def test_it_falls_back_to_a_word_when_the_title_slugs_to_nothing
        assert_equal "listing", ListingSlug.base("—")
        assert_equal "listing", ListingSlug.first_free("—", [])
      end

      def test_its_base_ignores_what_is_already_taken
        assert_equal "harbour-at-dusk", ListingSlug.base("Harbour at Dusk")
      end
    end
  end
end

require "test_helper"

module Domain
  module Listings
    class ListingSlugTest < ActiveSupport::TestCase
      test "it slugs the title" do
        assert_equal "harbour-at-dusk", ListingSlug.first_free("Harbour at Dusk", [])
      end

      test "it drops punctuation and edge separators" do
        assert_equal "study-no-4", ListingSlug.base("  Study, No. 4!  ")
      end

      test "it numbers a slug another listing already holds" do
        assert_equal "harbour-at-dusk-2", ListingSlug.first_free("Harbour at Dusk", ["harbour-at-dusk"])
      end

      test "it keeps counting past a numbered slug" do
        taken = ["harbour-at-dusk", "harbour-at-dusk-2", "harbour-at-dusk-3"]

        assert_equal "harbour-at-dusk-4", ListingSlug.first_free("Harbour at Dusk", taken)
      end

      test "it ignores slugs another title holds" do
        assert_equal "harbour-at-dusk", ListingSlug.first_free("Harbour at Dusk", ["morning-tide"])
      end

      test "it falls back to a word when the title slugs to nothing" do
        assert_equal "listing", ListingSlug.base("—")
        assert_equal "listing", ListingSlug.first_free("—", [])
      end

      test "its base ignores what is already taken" do
        assert_equal "harbour-at-dusk", ListingSlug.base("Harbour at Dusk")
      end
    end
  end
end

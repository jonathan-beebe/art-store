require "test_helper"

module Domain
  module Listings
    class ListingDraftTest < ActiveSupport::TestCase
      def test_a_complete_form_has_no_errors
        assert_empty ListingDraft.errors_for(fields)
      end

      def test_a_title_is_required
        assert_equal "Enter a title.", ListingDraft.errors_for(fields(title: "   "))[:title]
      end

      def test_a_title_has_a_length_limit
        errors = ListingDraft.errors_for(fields(title: "a" * 256))

        assert_equal "Keep the title under 255 characters.", errors[:title]
      end

      def test_a_description_has_a_length_limit
        errors = ListingDraft.errors_for(fields(description: "a" * 5_001))

        assert_equal "Keep the description under 5000 characters.", errors[:description]
      end

      def test_the_price_is_an_amount_in_dollars
        message = "The price is an amount in dollars, like 249.00."

        assert_equal message, ListingDraft.errors_for(fields(price: "free"))[:price]
        assert_equal message, ListingDraft.errors_for(fields(price: "$249"))[:price]
        assert_equal message, ListingDraft.errors_for(fields(price: "249.005"))[:price]
        assert_equal message, ListingDraft.errors_for(fields(price: ""))[:price]
      end

      def test_a_whole_dollar_price_needs_no_decimals
        assert_empty ListingDraft.errors_for(fields(price: "249"))
      end

      def test_the_quantity_is_a_whole_number_within_range
        message = "The quantity is a whole number from 0 to 999."

        assert_equal message, ListingDraft.errors_for(fields(quantity: "-1"))[:quantity]
        assert_equal message, ListingDraft.errors_for(fields(quantity: "1.5"))[:quantity]
        assert_equal message, ListingDraft.errors_for(fields(quantity: "1000"))[:quantity]
      end

      def test_a_sold_out_edition_may_be_zero
        assert_empty ListingDraft.errors_for(fields(quantity: "0"))
      end

      def test_an_upload_that_is_not_an_image_is_refused
        errors = ListingDraft.errors_for(fields(image_content_type: "application/pdf"))

        assert_equal "Upload an image file.", errors[:image]
      end

      def test_an_image_upload_is_accepted
        assert_empty ListingDraft.errors_for(fields(image_content_type: "image/png"))
      end

      def test_a_form_with_no_upload_asks_for_none
        assert_empty ListingDraft.errors_for(fields(image_content_type: nil))
      end

      def test_it_converts_the_price_to_cents
        assert_equal 24_900, ListingDraft.from(fields).price.cents
      end

      def test_it_trims_the_text_a_seller_typed
        draft = ListingDraft.from(fields(title: "  Harbour at Dusk  "))

        assert_equal "Harbour at Dusk", draft.title
      end

      def test_a_field_left_blank_is_stored_as_nothing
        draft = ListingDraft.from(fields(medium: "", dimensions: "   "))

        assert_nil draft.medium
        assert_nil draft.dimensions
      end

      def test_it_reads_as_listing_columns
        attributes = ListingDraft.from(fields).attributes

        assert_equal "Harbour at Dusk", attributes[:title]
        assert_equal 24_900, attributes[:price_cents]
        assert_equal 2, attributes[:quantity]
        refute_includes attributes.keys, :status
        refute_includes attributes.keys, :slug
      end

      private

      def fields(**overrides)
        {
          title: "Harbour at Dusk",
          description: "Oil on canvas.",
          medium: "Oil",
          dimensions: "40 x 60 cm",
          price: "249.00",
          quantity: "2"
        }.merge(overrides)
      end
    end
  end
end

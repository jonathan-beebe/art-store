require "test_helper"

module Domain
  module Listings
    class ListingDraftTest < ActiveSupport::TestCase
      test "a complete form has no errors" do
        assert_empty ListingDraft.errors_for(fields)
      end

      test "a title is required" do
        assert_equal "Enter a title.", ListingDraft.errors_for(fields(title: "   "))[:title]
      end

      test "a title has a length limit" do
        errors = ListingDraft.errors_for(fields(title: "a" * 256))

        assert_equal "Keep the title under 255 characters.", errors[:title]
      end

      test "a description has a length limit" do
        errors = ListingDraft.errors_for(fields(description: "a" * 5_001))

        assert_equal "Keep the description under 5000 characters.", errors[:description]
      end

      test "the price is an amount in dollars" do
        message = "The price is an amount in dollars, like 249.00."

        assert_equal message, ListingDraft.errors_for(fields(price: "free"))[:price]
        assert_equal message, ListingDraft.errors_for(fields(price: "$249"))[:price]
        assert_equal message, ListingDraft.errors_for(fields(price: "249.005"))[:price]
        assert_equal message, ListingDraft.errors_for(fields(price: ""))[:price]
      end

      test "a whole dollar price needs no decimals" do
        assert_empty ListingDraft.errors_for(fields(price: "249"))
      end

      test "the quantity is a whole number within range" do
        message = "The quantity is a whole number from 0 to 999."

        assert_equal message, ListingDraft.errors_for(fields(quantity: "-1"))[:quantity]
        assert_equal message, ListingDraft.errors_for(fields(quantity: "1.5"))[:quantity]
        assert_equal message, ListingDraft.errors_for(fields(quantity: "1000"))[:quantity]
      end

      test "a sold out edition may be zero" do
        assert_empty ListingDraft.errors_for(fields(quantity: "0"))
      end

      test "an upload that is not an image is refused" do
        errors = ListingDraft.errors_for(fields(image_content_type: "application/pdf"))

        assert_equal "Upload an image file.", errors[:image]
      end

      test "an image upload is accepted" do
        assert_empty ListingDraft.errors_for(fields(image_content_type: "image/png"))
      end

      test "a form with no upload asks for none" do
        assert_empty ListingDraft.errors_for(fields(image_content_type: nil))
      end

      test "it converts the price to cents" do
        assert_equal 24_900, ListingDraft.from(fields).price.cents
      end

      test "it trims the text a seller typed" do
        draft = ListingDraft.from(fields(title: "  Harbour at Dusk  "))

        assert_equal "Harbour at Dusk", draft.title
      end

      test "a field left blank is stored as nothing" do
        draft = ListingDraft.from(fields(medium: "", dimensions: "   "))

        assert_nil draft.medium
        assert_nil draft.dimensions
      end

      test "it reads as listing columns" do
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

require "commerce_test_case"

module Listings
  class CreateListingTest < CommerceTestCase
    def test_a_new_listing_starts_as_a_draft
      created = CreateListing.new.call(seller: seller, draft: draft)

      assert_equal Domain::Listings::ListingStatus::DRAFT, created.status
    end

    def test_it_stores_the_typed_fields_and_the_price_in_cents
      created = CreateListing.new.call(seller: seller, draft: draft)

      assert_equal "Harbour at Dusk", created.title
      assert_equal "Oil", created.medium
      assert_equal "40 x 60 cm", created.dimensions
      assert_equal 24_900, created.price_cents
      assert_equal 2, created.quantity
    end

    def test_it_belongs_to_the_seller_who_created_it
      artist = seller

      created = CreateListing.new.call(seller: artist, draft: draft)

      assert_equal artist, created.seller
    end

    def test_it_slugs_the_title
      created = CreateListing.new.call(seller: seller, draft: draft)

      assert_equal "harbour-at-dusk", created.slug
    end

    def test_a_title_another_listing_already_slugged_is_numbered
      CreateListing.new.call(seller: seller, draft: draft)

      created = CreateListing.new.call(seller: seller, draft: draft)

      assert_equal "harbour-at-dusk-2", created.slug
    end

    def test_it_attaches_an_uploaded_image
      created = CreateListing.new.call(seller: seller, draft: draft, image: uploaded_image)

      assert_predicate created.image, :attached?
      assert_equal "image/png", created.image.content_type
    end

    def test_a_listing_with_no_upload_carries_no_image
      created = CreateListing.new.call(seller: seller, draft: draft)

      refute_predicate created.image, :attached?
    end

    private

    def draft(**overrides)
      Domain::Listings::ListingDraft.from({
        title: "Harbour at Dusk",
        description: "Oil on canvas.",
        medium: "Oil",
        dimensions: "40 x 60 cm",
        price: "249.00",
        quantity: "2"
      }.merge(overrides))
    end

    def uploaded_image
      Rack::Test::UploadedFile.new(
        StringIO.new("\x89PNG\r\n\x1a\n"), "image/png", true, original_filename: "harbour.png"
      )
    end
  end
end

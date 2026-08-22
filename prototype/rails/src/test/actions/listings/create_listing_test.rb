require "commerce_test_case"

module Listings
  class CreateListingTest < CommerceTestCase
    test "a new listing starts as a draft" do
      created = CreateListing.new.call(seller: seller, draft: draft)

      assert_equal Domain::Listings::ListingStatus::DRAFT, created.status
    end

    test "it stores the typed fields and the price in cents" do
      created = CreateListing.new.call(seller: seller, draft: draft)

      assert_equal "Harbour at Dusk", created.title
      assert_equal "Oil", created.medium
      assert_equal "40 x 60 cm", created.dimensions
      assert_equal 24_900, created.price_cents
      assert_equal 2, created.quantity
    end

    test "it belongs to the seller who created it" do
      artist = seller

      created = CreateListing.new.call(seller: artist, draft: draft)

      assert_equal artist, created.seller
    end

    test "it slugs the title" do
      created = CreateListing.new.call(seller: seller, draft: draft)

      assert_equal "harbour-at-dusk", created.slug
    end

    test "a title another listing already slugged is numbered" do
      CreateListing.new.call(seller: seller, draft: draft)

      created = CreateListing.new.call(seller: seller, draft: draft)

      assert_equal "harbour-at-dusk-2", created.slug
    end

    test "it attaches an uploaded image" do
      created = CreateListing.new.call(seller: seller, draft: draft, image: uploaded_image)

      assert_predicate created.image, :attached?
      assert_equal "image/png", created.image.content_type
    end

    test "a listing with no upload carries no image" do
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

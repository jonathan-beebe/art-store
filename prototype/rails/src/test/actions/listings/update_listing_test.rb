require "test_helper"

module Listings
  class UpdateListingTest < ActiveSupport::TestCase
    test "it writes the edited fields" do
      art = CreateListing.new.call(seller: create_seller, draft: draft)

      UpdateListing.new.call(listing: art, draft: draft(title: "Harbour at Dawn", price: "300.50"))

      assert_equal "Harbour at Dawn", art.reload.title
      assert_equal 30_050, art.price_cents
    end

    test "a retitled listing keeps its slug" do
      art = CreateListing.new.call(seller: create_seller, draft: draft)

      UpdateListing.new.call(listing: art, draft: draft(title: "Harbour at Dawn"))

      assert_equal "harbour-at-dusk", art.reload.slug
    end

    test "it leaves the status alone" do
      art = CreateListing.new.call(seller: create_seller, draft: draft)
      art.update!(status: Domain::Listings::ListingStatus::FOR_SALE)

      UpdateListing.new.call(listing: art, draft: draft(title: "Harbour at Dawn"))

      assert_equal Domain::Listings::ListingStatus::FOR_SALE, art.reload.status
    end

    test "a new upload replaces the image" do
      art = CreateListing.new.call(seller: create_seller, draft: draft, image: uploaded_image("first.png"))

      UpdateListing.new.call(listing: art, draft: draft, image: uploaded_image("second.png"))

      assert_equal "second.png", art.reload.image.filename.to_s
    end

    test "an edit with no upload keeps the image" do
      art = CreateListing.new.call(seller: create_seller, draft: draft, image: uploaded_image("first.png"))

      UpdateListing.new.call(listing: art, draft: draft(title: "Harbour at Dawn"))

      assert_equal "first.png", art.reload.image.filename.to_s
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

    def uploaded_image(filename)
      Rack::Test::UploadedFile.new(
        StringIO.new("\x89PNG\r\n\x1a\n"), "image/png", true, original_filename: filename
      )
    end
  end
end

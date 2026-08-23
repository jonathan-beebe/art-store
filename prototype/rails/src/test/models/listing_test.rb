require "test_helper"

class ListingTest < ActiveSupport::TestCase
  test "a listing without an upload renders a placeholder image" do
    record = create_listing(title: "Blue Heron")

    assert record.image_url.start_with?("data:image/svg+xml;base64,")
  end

  test "an uploaded image is served through Active Storage" do
    record = create_listing(title: "Blue Heron")
    record.image.attach(io: StringIO.new("<svg/>"), filename: "heron.svg", content_type: "image/svg+xml")

    assert_match %r{\A/rails/active_storage/blobs/}, record.image_url
  end
end

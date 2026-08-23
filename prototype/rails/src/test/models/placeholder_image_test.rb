require "test_helper"

class PlaceholderImageTest < ActiveSupport::TestCase
  test "same title renders the same svg" do
    assert_equal PlaceholderImage.svg("Blue Heron"), PlaceholderImage.svg("Blue Heron")
  end

  test "different titles render different svgs" do
    refute_equal PlaceholderImage.svg("Blue Heron"), PlaceholderImage.svg("Red Fox")
  end

  test "svg carries the title as an accessible label" do
    svg = PlaceholderImage.svg("Mug & Bowl")

    assert_includes svg, 'aria-label="Mug &amp; Bowl"'
    assert svg.lstrip.start_with?("<svg")
  end

  test "data uri is base64 svg" do
    uri = PlaceholderImage.data_uri("Blue Heron")
    prefix = "data:image/svg+xml;base64,"

    assert uri.start_with?(prefix)
    assert_equal PlaceholderImage.svg("Blue Heron"), Base64.strict_decode64(uri.delete_prefix(prefix))
  end
end

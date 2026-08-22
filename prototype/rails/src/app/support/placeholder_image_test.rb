require "minitest/autorun"
require_relative "placeholder_image"

class PlaceholderImageTest < Minitest::Test
  def test_same_title_renders_the_same_svg
    assert_equal PlaceholderImage.svg("Blue Heron"), PlaceholderImage.svg("Blue Heron")
  end

  def test_different_titles_render_different_svgs
    refute_equal PlaceholderImage.svg("Blue Heron"), PlaceholderImage.svg("Red Fox")
  end

  def test_svg_carries_the_title_as_an_accessible_label
    svg = PlaceholderImage.svg("Mug & Bowl")

    assert_includes svg, 'aria-label="Mug &amp; Bowl"'
    assert svg.lstrip.start_with?("<svg")
  end

  def test_data_uri_is_base64_svg
    uri = PlaceholderImage.data_uri("Blue Heron")
    prefix = "data:image/svg+xml;base64,"

    assert uri.start_with?(prefix)
    assert_equal PlaceholderImage.svg("Blue Heron"), Base64.strict_decode64(uri.delete_prefix(prefix))
  end
end

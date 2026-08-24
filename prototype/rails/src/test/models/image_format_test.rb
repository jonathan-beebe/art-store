require "test_helper"

class ImageFormatTest < ActiveSupport::TestCase
  test "sniffs a PNG from its signature" do
    assert_equal :png, ImageFormat.sniff("\x89PNG\r\n\x1a\n\x00\x00".b)
  end

  test "sniffs a JPEG from its signature" do
    assert_equal :jpeg, ImageFormat.sniff("\xff\xd8\xff\xe0\x00\x10".b)
  end

  test "sniffs a GIF87a from its signature" do
    assert_equal :gif, ImageFormat.sniff("GIF87a rest of file")
  end

  test "sniffs a GIF89a from its signature" do
    assert_equal :gif, ImageFormat.sniff("GIF89a rest of file")
  end

  test "sniffs a WebP from its RIFF/WEBP signature" do
    assert_equal :webp, ImageFormat.sniff("RIFF____WEBPVP8 ")
  end

  test "a RIFF file that is not WebP sniffs as nothing" do
    assert_nil ImageFormat.sniff("RIFF____AVI rest")
  end

  test "SVG bytes sniff as nothing — markup carries no magic bytes" do
    assert_nil ImageFormat.sniff('<svg xmlns="http://www.w3.org/2000/svg"></svg>')
  end

  test "HTML bytes sniff as nothing" do
    assert_nil ImageFormat.sniff("<html><body>evil</body></html>")
  end

  test "PDF bytes sniff as nothing" do
    assert_nil ImageFormat.sniff("%PDF-1.4\n")
  end

  test "a zip's local file header sniffs as nothing" do
    assert_nil ImageFormat.sniff("PK\x03\x04".b)
  end

  test "nil sniffs as nothing" do
    assert_nil ImageFormat.sniff(nil)
  end

  test "an empty string sniffs as nothing" do
    assert_nil ImageFormat.sniff("")
  end

  test "a buffer shorter than any signature sniffs as nothing" do
    assert_nil ImageFormat.sniff("\x89P".b)
  end
end

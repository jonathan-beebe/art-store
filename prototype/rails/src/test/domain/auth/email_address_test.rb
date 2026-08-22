require "test_helper"

module Domain
  module Auth
    class EmailAddressTest < ActiveSupport::TestCase
      test "normalize lowercases an address" do
        assert_equal "artist@example.com", EmailAddress.normalize("Artist@Example.COM")
      end

      test "normalize trims surrounding whitespace" do
        assert_equal "artist@example.com", EmailAddress.normalize("  artist@example.com\n")
      end

      test "normalize leaves an already normal address alone" do
        assert_equal "artist@example.com", EmailAddress.normalize("artist@example.com")
      end

      test "normalize turns a missing address into an empty string" do
        assert_equal "", EmailAddress.normalize(nil)
      end

      test "an address with a local part and a dotted domain is valid" do
        assert EmailAddress.valid?("artist@example.com")
      end

      test "an address without an at sign is invalid" do
        refute EmailAddress.valid?("artist.example.com")
      end

      test "an address without a dotted domain is invalid" do
        refute EmailAddress.valid?("artist@example")
      end

      test "an address carrying whitespace is invalid" do
        refute EmailAddress.valid?("artist name@example.com")
      end

      test "a blank address is invalid" do
        refute EmailAddress.valid?("   ")
      end
    end
  end
end

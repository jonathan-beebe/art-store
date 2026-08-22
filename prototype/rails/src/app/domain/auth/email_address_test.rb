# Runs without Rails: ruby -Iapp app/domain/auth/email_address_test.rb
require "minitest/autorun"
require_relative "email_address"

module Domain
  module Auth
    class EmailAddressTest < Minitest::Test
      def test_normalize_lowercases_an_address
        assert_equal "artist@example.com", EmailAddress.normalize("Artist@Example.COM")
      end

      def test_normalize_trims_surrounding_whitespace
        assert_equal "artist@example.com", EmailAddress.normalize("  artist@example.com\n")
      end

      def test_normalize_leaves_an_already_normal_address_alone
        assert_equal "artist@example.com", EmailAddress.normalize("artist@example.com")
      end

      def test_normalize_turns_a_missing_address_into_an_empty_string
        assert_equal "", EmailAddress.normalize(nil)
      end

      def test_an_address_with_a_local_part_and_a_dotted_domain_is_valid
        assert EmailAddress.valid?("artist@example.com")
      end

      def test_an_address_without_an_at_sign_is_invalid
        refute EmailAddress.valid?("artist.example.com")
      end

      def test_an_address_without_a_dotted_domain_is_invalid
        refute EmailAddress.valid?("artist@example")
      end

      def test_an_address_carrying_whitespace_is_invalid
        refute EmailAddress.valid?("artist name@example.com")
      end

      def test_a_blank_address_is_invalid
        refute EmailAddress.valid?("   ")
      end
    end
  end
end

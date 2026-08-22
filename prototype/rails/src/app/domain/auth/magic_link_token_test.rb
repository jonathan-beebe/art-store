# Runs without Rails: ruby -Iapp app/domain/auth/magic_link_token_test.rb
require "minitest/autorun"
require_relative "magic_link_token"

module Domain
  module Auth
    class MagicLinkTokenTest < Minitest::Test
      def test_digest_is_the_sha256_of_the_token
        assert_equal Digest::SHA256.hexdigest("abc"), MagicLinkToken.digest("abc")
      end

      def test_the_same_token_digests_to_the_same_value
        assert_equal MagicLinkToken.digest("abc"), MagicLinkToken.digest("abc")
      end

      def test_different_tokens_digest_to_different_values
        refute_equal MagicLinkToken.digest("abc"), MagicLinkToken.digest("abd")
      end
    end
  end
end

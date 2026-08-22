require "test_helper"

module Domain
  module Auth
    class MagicLinkTokenTest < ActiveSupport::TestCase
      test "digest is the sha256 of the token" do
        assert_equal Digest::SHA256.hexdigest("abc"), MagicLinkToken.digest("abc")
      end

      test "the same token digests to the same value" do
        assert_equal MagicLinkToken.digest("abc"), MagicLinkToken.digest("abc")
      end

      test "different tokens digest to different values" do
        refute_equal MagicLinkToken.digest("abc"), MagicLinkToken.digest("abd")
      end
    end
  end
end

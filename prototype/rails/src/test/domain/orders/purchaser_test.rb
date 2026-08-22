require "test_helper"

module Domain
  module Orders
    class PurchaserTest < ActiveSupport::TestCase
      test "a purchaser with a verified email is verified" do
        purchaser = Purchaser.new(id: 1, email: "ada@example.test", email_verified_at: Time.utc(2026, 8, 19))

        assert_predicate purchaser, :email_verified?
      end

      test "an anonymous visitor is not verified" do
        refute_predicate Purchaser.new(id: 2, email: nil, email_verified_at: nil), :email_verified?
      end

      test "a purchaser who has not followed the link yet is not verified" do
        refute_predicate Purchaser.new(id: 3, email: "guest@example.test", email_verified_at: nil), :email_verified?
      end
    end
  end
end

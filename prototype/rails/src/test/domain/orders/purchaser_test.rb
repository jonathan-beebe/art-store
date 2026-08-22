require "test_helper"

module Domain
  module Orders
    class PurchaserTest < ActiveSupport::TestCase
      def test_a_purchaser_with_a_verified_email_is_verified
        purchaser = Purchaser.new(id: 1, email: "ada@example.test", email_verified_at: Time.utc(2026, 8, 19))

        assert_predicate purchaser, :email_verified?
      end

      def test_an_anonymous_visitor_is_not_verified
        refute_predicate Purchaser.new(id: 2, email: nil, email_verified_at: nil), :email_verified?
      end

      def test_a_purchaser_who_has_not_followed_the_link_yet_is_not_verified
        refute_predicate Purchaser.new(id: 3, email: "guest@example.test", email_verified_at: nil), :email_verified?
      end
    end
  end
end

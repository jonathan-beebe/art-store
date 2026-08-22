require "identity_test_case"

module Auth
  class ClaimSellerIdentityTest < IdentityTestCase
    test "a first link for an address creates the seller" do
      seller = claim("newcomer@example.com")

      assert_equal "newcomer@example.com", seller.email
      assert_equal 1, Seller.count
    end

    test "a first link marks the address verified" do
      freeze_time do
        assert_equal Time.current, claim("newcomer@example.com").email_verified_at
      end
    end

    test "a later link returns the seller already holding the address" do
      existing = create_seller

      assert_equal existing, claim(existing.email)
      assert_equal 1, Seller.count
    end

    test "a later link leaves the original verification time alone" do
      existing = create_seller(email_verified_at: 3.days.ago)

      assert_equal existing.email_verified_at.to_i, claim(existing.email).email_verified_at.to_i
    end

    test "an address differing only in case reaches the same seller" do
      existing = create_seller(email: "artist@example.com")

      assert_equal existing, claim("ARTIST@Example.com")
    end

    private

    def claim(email)
      ClaimSellerIdentity.new.call(email: email)
    end
  end
end

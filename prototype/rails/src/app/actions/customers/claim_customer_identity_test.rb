require "identity_test_case"

module Customers
  class ClaimCustomerIdentityTest < IdentityTestCase
    test "a visitor with no cookie and no account gets a new verified customer" do
      customer = claim(email: "newcomer@example.com", current: nil)

      assert_equal "newcomer@example.com", customer.email
      assert_predicate customer.email_verified_at, :present?
    end

    test "a visitor with no cookie signs in to the account holding the address" do
      existing = create_verified_customer

      assert_equal existing, claim(email: existing.email, current: nil)
      assert_equal 1, Customer.count
    end

    test "an anonymous visitor with no account claims the anonymous row in place" do
      anonymous = create_anonymous_customer

      customer = claim(email: "newcomer@example.com", current: anonymous)

      assert_equal anonymous, customer
      assert_equal "newcomer@example.com", customer.email
      assert_equal 1, Customer.count
    end

    test "claiming in place marks the address verified" do
      freeze_time do
        customer = claim(email: "newcomer@example.com", current: create_anonymous_customer)

        assert_equal Time.current, customer.email_verified_at
      end
    end

    test "an anonymous visitor whose address already has an account merges into it" do
      anonymous = create_anonymous_customer
      existing = create_verified_customer

      assert_equal existing, claim(email: existing.email, current: anonymous)
      assert_equal existing, CustomerMerge.sole.customer
    end

    test "a cookie already pointing at the account writes no merge" do
      existing = create_verified_customer

      assert_equal existing, claim(email: existing.email, current: existing)
      assert_equal 0, CustomerMerge.count
    end

    test "an address left unverified by a guest checkout is settled by the link" do
      guest = Customer.create!(email: "guest@example.com")

      customer = claim(email: "guest@example.com", current: nil)

      assert_equal guest, customer
      assert_predicate customer.email_verified_at, :present?
    end

    test "an address differing only in case reaches the same account" do
      existing = create_verified_customer(email: "buyer@example.com")

      assert_equal existing, claim(email: "BUYER@Example.com", current: nil)
    end

    private

    def claim(email:, current:)
      ClaimCustomerIdentity.new.call(email: email, current: current)
    end
  end
end

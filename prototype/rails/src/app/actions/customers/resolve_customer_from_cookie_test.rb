require "identity_test_case"

module Customers
  class ResolveCustomerFromCookieTest < IdentityTestCase
    test "it finds the customer the cookie points at" do
      customer = create_anonymous_customer

      assert_equal customer, resolve(customer.id)
    end

    test "it follows a merge so a stale cookie lands on the verified customer" do
      anonymous = create_anonymous_customer
      verified = create_verified_customer
      CustomerMerge.create!(anonymous_customer: anonymous, customer: verified)

      assert_equal verified, resolve(anonymous.id)
    end

    test "it resolves a cookie carrying the id as a string" do
      customer = create_anonymous_customer

      assert_equal customer, resolve(customer.id.to_s)
    end

    test "it resolves nothing for a customer that no longer exists" do
      assert_nil resolve(404)
    end

    test "it resolves nothing when there is no cookie" do
      assert_nil resolve(nil)
    end

    test "it resolves nothing for a cookie that is not an id" do
      assert_nil resolve("../../etc/passwd")
    end

    test "it resolves nothing for a cookie holding a meaningless id" do
      assert_nil resolve(0)
    end

    private

    def resolve(cookie_value)
      ResolveCustomerFromCookie.new.call(cookie_value)
    end
  end
end

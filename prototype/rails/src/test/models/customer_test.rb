require "identity_test_case"

class CustomerTest < IdentityTestCase
  test "a customer with no address is anonymous" do
    assert_predicate create_anonymous_customer, :anonymous?
  end

  test "a customer holding an address is not anonymous" do
    refute_predicate create_verified_customer, :anonymous?
  end

  test "the address is normalized on the way in" do
    customer = create_verified_customer(email: "  Buyer@Example.COM ")

    assert_equal "buyer@example.com", customer.email
  end

  test "an anonymous customer keeps a null address rather than a blank one" do
    assert_nil create_anonymous_customer.email
  end

  test "several anonymous customers coexist under the unique address index" do
    create_anonymous_customer
    create_anonymous_customer

    assert_equal 2, Customer.count
  end

  test "verified lists only the customers holding an address" do
    verified = create_verified_customer
    create_anonymous_customer

    assert_equal [verified], Customer.verified.to_a
  end
end

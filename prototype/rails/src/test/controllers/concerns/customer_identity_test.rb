require "test_helper"

# The concern has no page of its own; the storefront is where every request runs
# it.
class CustomerIdentityConcernTest < ActionDispatch::IntegrationTest
  test "a first storefront visit creates the anonymous customer" do
    get root_path

    assert_predicate Customer.sole, :anonymous?
  end

  test "a first storefront visit carries the identity in a signed cookie" do
    get root_path

    assert_equal Customer.sole.id, signed_cookie(CustomerIdentity::COOKIE)
  end

  test "the identity cookie is not readable as plain text" do
    get root_path

    refute_equal Customer.sole.id.to_s, cookies[CustomerIdentity::COOKIE.to_s]
  end

  test "a later visit resolves the same customer rather than creating another" do
    get root_path
    get root_path

    assert_equal 1, Customer.count
  end

  test "a cookie pointing at a customer that no longer exists starts a fresh identity" do
    get root_path
    Customer.sole.destroy!

    get root_path

    assert_equal Customer.sole.id, signed_cookie(CustomerIdentity::COOKIE)
  end

  test "the seller portal creates no customer" do
    get seller_root_path

    assert_equal 0, Customer.count
  end

  test "asking for a sign-in link creates no customer" do
    post customer_send_magic_link_path, params: { email: "buyer@example.com" }

    assert_equal 0, Customer.count
  end
end

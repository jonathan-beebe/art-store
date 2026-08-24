require "test_helper"

class Admin::CustomerBlocksControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor blocks nobody" do
    customer = create_verified_customer

    post admin_customer_blocks_path(customer), params: { reason: "Chargeback fraud." }

    assert_redirected_to admin_login_path
    refute_predicate customer.reload, :blocked?
  end

  test "blocking a customer names the reason" do
    sign_in_as_admin
    customer = create_verified_customer

    post admin_customer_blocks_path(customer), params: { reason: "Chargeback fraud." }

    assert_redirected_to admin_customer_path(customer)
    assert_predicate customer.reload, :blocked?
    assert_equal "Chargeback fraud.", customer.blocked_reason
    follow_redirect!
    assert_select "[data-flash=notice]", text: "Customer blocked."
  end

  test "a customer already blocked is not blocked a second time" do
    sign_in_as_admin
    customer = create_verified_customer
    post admin_customer_blocks_path(customer), params: { reason: "First." }

    post admin_customer_blocks_path(customer), params: { reason: "Second." }

    follow_redirect!
    assert_select "[data-flash=alert]", text: "customer #{customer.id} is already blocked"
    assert_equal 1, customer.reload.blocks.count
  end

  test "lifting a block restores shopping" do
    sign_in_as_admin
    customer = create_verified_customer
    post admin_customer_blocks_path(customer), params: { reason: "Chargeback fraud." }

    post lift_admin_customer_blocks_path(customer)

    assert_redirected_to admin_customer_path(customer)
    refute_predicate customer.reload, :blocked?
    follow_redirect!
    assert_select "[data-flash=notice]", text: "Block lifted."
  end

  test "a customer nobody blocked cannot be lifted" do
    sign_in_as_admin
    customer = create_verified_customer

    post lift_admin_customer_blocks_path(customer)

    follow_redirect!
    assert_select "[data-flash=alert]", text: "customer #{customer.id} is not blocked"
  end
end

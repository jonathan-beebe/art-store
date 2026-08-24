require "test_helper"

class CustomerBlockTest < ActiveSupport::TestCase
  test "an unlifted block is active" do
    assert_predicate build, :active?
  end

  test "a lifted block is not active" do
    refute_predicate build(lifted_at: Time.current), :active?
  end

  test "active narrows to the blocks nobody has lifted" do
    customer = create_verified_customer
    admin = create_admin
    lifted = CustomerBlock.create!(customer: customer, admin: admin, reason: "First", lifted_at: Time.current)
    standing = CustomerBlock.create!(customer: customer, admin: admin, reason: "Second")

    assert_equal [ standing ], CustomerBlock.active.to_a
    assert_not_includes CustomerBlock.active, lifted
  end

  test "a reason is required" do
    refute_predicate build(reason: ""), :valid?
  end

  private

  def build(**overrides)
    CustomerBlock.new({ customer: create_verified_customer, admin: create_admin, reason: "Chargeback fraud" }.merge(overrides))
  end
end

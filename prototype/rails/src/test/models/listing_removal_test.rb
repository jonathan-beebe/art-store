require "test_helper"

class ListingRemovalTest < ActiveSupport::TestCase
  test "a temporary removal can be lifted" do
    assert_predicate build(kind: :temporary), :liftable?
  end

  test "a permanent removal cannot be lifted" do
    refute_predicate build(kind: :permanent), :liftable?
  end

  test "an unlifted removal is active" do
    assert_predicate build, :active?
  end

  test "a lifted removal is not active" do
    refute_predicate build(lifted_at: Time.current), :active?
  end

  test "active narrows to the removals nobody has lifted" do
    listing = create_listing
    admin = create_admin
    lifted = ListingRemoval.create!(listing: listing, admin: admin, kind: :temporary, reason: "First", lifted_at: Time.current)
    standing = ListingRemoval.create!(listing: listing, admin: admin, kind: :permanent, reason: "Second")

    assert_equal [ standing ], ListingRemoval.active.to_a
    assert_not_includes ListingRemoval.active, lifted
  end

  test "a reason is required" do
    refute_predicate build(reason: ""), :valid?
  end

  private

  def build(**overrides)
    ListingRemoval.new({
      listing: create_listing, admin: create_admin, kind: :temporary, reason: "Reported as counterfeit"
    }.merge(overrides))
  end
end

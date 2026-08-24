require "test_helper"

class Admin::ListingRemovalsControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor removes nothing" do
    listing = create_listing

    post admin_listing_removals_path(listing), params: { kind: "temporary", reason: "Reported." }

    assert_redirected_to admin_login_path
    refute_predicate listing.reload, :actively_removed?
  end

  test "removing a listing takes it off the storefront and names the reason" do
    sign_in_as_admin
    listing = create_listing(status: :for_sale)

    post admin_listing_removals_path(listing), params: { kind: "temporary", reason: "Reported as counterfeit." }

    assert_redirected_to admin_listing_path(listing)
    assert_predicate listing.reload, :actively_removed?
    assert_equal "Reported as counterfeit.", listing.active_removal.reason
    assert_equal "temporary", listing.active_removal.kind
    follow_redirect!
    assert_select "[data-flash=notice]", text: "Listing removed."
  end

  test "a listing already removed is not removed a second time" do
    sign_in_as_admin
    listing = create_listing
    post admin_listing_removals_path(listing), params: { kind: "temporary", reason: "First." }

    post admin_listing_removals_path(listing), params: { kind: "permanent", reason: "Second." }

    follow_redirect!
    assert_select "[data-flash=alert]", text: "listing #{listing.id} is already removed"
    assert_equal 1, listing.reload.removals.count
  end

  test "lifting a temporary removal restores the storefront" do
    sign_in_as_admin
    listing = create_listing(status: :for_sale)
    post admin_listing_removals_path(listing), params: { kind: "temporary", reason: "Reported." }

    post lift_admin_listing_removals_path(listing)

    assert_redirected_to admin_listing_path(listing)
    refute_predicate listing.reload, :actively_removed?
    follow_redirect!
    assert_select "[data-flash=notice]", text: "Removal lifted."
  end

  test "a permanent removal is refused" do
    sign_in_as_admin
    listing = create_listing
    post admin_listing_removals_path(listing), params: { kind: "permanent", reason: "Counterfeit." }

    post lift_admin_listing_removals_path(listing)

    follow_redirect!
    assert_select "[data-flash=alert]", text: "a permanent removal cannot be lifted"
    assert_predicate listing.reload, :actively_removed?
  end

  test "a listing nobody removed cannot be lifted" do
    sign_in_as_admin
    listing = create_listing

    post lift_admin_listing_removals_path(listing)

    follow_redirect!
    assert_select "[data-flash=alert]", text: "listing #{listing.id} is not removed"
  end
end

require "test_helper"

# The after_action itself, over real requests, so the whole chain —
# `request.route_uri_pattern`, `PageView.countable?`, `PageViewCount.record!`
# — is proven together rather than each piece in isolation.
class PageViewRollupTest < ActionDispatch::IntegrationTest
  test "a GET that answers with an HTML page rolls up one hit" do
    get root_path

    row = PageViewCount.sole
    assert_equal "shop", row.site
    assert_equal 1, row.count
  end

  test "a second hit the same day increments the row rather than adding one" do
    get root_path
    get root_path

    assert_equal 1, PageViewCount.count
    assert_equal 2, PageViewCount.sole.count
  end

  test "the pattern is stored, not the concrete URL" do
    listing = create_listing

    get shop_listing_path(slug: listing.slug)

    row = PageViewCount.find_by(path_pattern: "/art/:slug(.:format)")
    refute_nil row
    assert_not_includes PageViewCount.pluck(:path_pattern), "/art/#{listing.slug}"
  end

  test "two different listings share one row for the pattern" do
    get shop_listing_path(slug: create_listing.slug)
    get shop_listing_path(slug: create_listing.slug)

    assert_equal 2, PageViewCount.find_by(path_pattern: "/art/:slug(.:format)").count
  end

  test "an unknown slug answers 404 and is not counted" do
    get shop_listing_path(slug: "nothing-here")

    assert_response :not_found
    assert_equal 0, PageViewCount.count
  end

  test "a path no route matches is not counted" do
    get "/this-path-does-not-exist"

    assert_equal 0, PageViewCount.count
  end

  test "a request a before_action turns away is not counted" do
    get admin_root_path

    assert_response :redirect
    assert_equal 0, PageViewCount.count
  end

  test "a redirect the action itself answers with is not counted" do
    token, _link = create_magic_link(actor_type: :customer)

    get verify_magic_link_path(token: token)

    assert_response :redirect
    assert_equal 0, PageViewCount.count
  end

  test "a non-GET request is not counted" do
    listing = create_listing

    post shop_toggle_favorite_path(slug: listing.slug)

    assert_equal 0, PageViewCount.count
  end

  test "the seller portal's own pages roll up under the seller site" do
    sign_in_as_seller

    get seller_root_path

    assert_equal "seller", PageViewCount.find_by(path_pattern: "/seller(.:format)")&.site
  end

  test "the admin sign-in page rolls up under the admin site" do
    get admin_login_path

    assert_equal "admin", PageViewCount.find_by(path_pattern: "/admin/login(.:format)")&.site
  end
end

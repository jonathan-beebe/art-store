require "test_helper"

class PageViewTest < ActiveSupport::TestCase
  test "a successful HTML GET is countable" do
    assert PageView.countable?(method: "GET", status: 200, content_type: "text/html")
  end

  test "the method check is case-insensitive" do
    assert PageView.countable?(method: "get", status: 200, content_type: "text/html")
  end

  test "a non-GET request is not countable" do
    refute PageView.countable?(method: "POST", status: 200, content_type: "text/html")
  end

  test "a status below 200 is not countable" do
    refute PageView.countable?(method: "GET", status: 101, content_type: "text/html")
  end

  test "a redirect is not countable" do
    refute PageView.countable?(method: "GET", status: 303, content_type: "text/html")
  end

  test "a status at or above 300 is not countable" do
    refute PageView.countable?(method: "GET", status: 404, content_type: "text/html")
  end

  test "a missing content type is not countable" do
    refute PageView.countable?(method: "GET", status: 200, content_type: nil)
  end

  test "a non-HTML content type is not countable" do
    refute PageView.countable?(method: "GET", status: 200, content_type: "application/json")
  end

  test "day reads the UTC calendar day" do
    assert_equal Date.new(2026, 8, 22), PageView.day(Time.utc(2026, 8, 22, 23, 59, 59))
  end

  test "day does not shift across a UTC day boundary" do
    assert_equal Date.new(2026, 8, 23), PageView.day(Time.utc(2026, 8, 23, 0, 0, 0))
  end

  test "the week is the seven days ending today" do
    assert_equal Date.new(2026, 8, 18)..Date.new(2026, 8, 24), PageView.week(Date.new(2026, 8, 24))
  end

  test "the week reaches back over the end of a month" do
    assert_equal Date.new(2026, 8, 27)..Date.new(2026, 9, 2), PageView.week(Date.new(2026, 9, 2))
  end

  test "the week reaches back over the end of a year" do
    assert_equal Date.new(2026, 12, 28)..Date.new(2027, 1, 3), PageView.week(Date.new(2027, 1, 3))
  end

  test "a portal prefix names its own site" do
    assert_equal "seller", PageView.site_for("/seller")
    assert_equal "seller", PageView.site_for("/seller/listings/:id")
    assert_equal "admin", PageView.site_for("/admin")
    assert_equal "admin", PageView.site_for("/admin/customers/:id")
  end

  test "everything else is the storefront" do
    assert_equal "shop", PageView.site_for("/")
    assert_equal "shop", PageView.site_for("/art/:slug")
    assert_equal "shop", PageView.site_for("/auth/magic/:token")
  end

  test "a path that merely starts with the letters of a prefix is not that portal" do
    assert_equal "shop", PageView.site_for("/sellers-guide")
    assert_equal "shop", PageView.site_for("/administration")
  end
end

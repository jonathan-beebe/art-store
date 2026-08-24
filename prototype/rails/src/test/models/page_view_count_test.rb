require "test_helper"

class PageViewCountTest < ActiveSupport::TestCase
  include IntegrationHelpers

  test "the first hit of a day inserts a row of one" do
    PageViewCount.record!(path_pattern: "/art/:slug", at: moment("2026-08-22 10:00:00"))

    row = PageViewCount.sole
    assert_equal "shop", row.site
    assert_equal "/art/:slug", row.path_pattern
    assert_equal Date.new(2026, 8, 22), row.day
    assert_equal 1, row.count
  end

  test "a later hit the same day increments the row rather than adding one" do
    PageViewCount.record!(path_pattern: "/art/:slug", at: moment("2026-08-22 10:00:00"))
    PageViewCount.record!(path_pattern: "/art/:slug", at: moment("2026-08-22 18:00:00"))

    assert_equal 2, PageViewCount.sole.count
  end

  test "the pattern is stored, not a concrete URL" do
    PageViewCount.record!(path_pattern: "/art/:slug", at: moment("2026-08-22 10:00:00"))

    assert_equal "/art/:slug", PageViewCount.sole.path_pattern
  end

  test "the same pattern the next day writes its own row" do
    PageViewCount.record!(path_pattern: "/art/:slug", at: moment("2026-08-22 10:00:00"))
    PageViewCount.record!(path_pattern: "/art/:slug", at: moment("2026-08-23 10:00:00"))

    assert_equal 2, PageViewCount.count
    assert_equal [ 1, 1 ], PageViewCount.order(:day).pluck(:count)
  end

  test "the site is derived from the pattern, seller and admin claim their prefixes" do
    PageViewCount.record!(path_pattern: "/art/:slug", at: moment("2026-08-22 10:00:00"))
    PageViewCount.record!(path_pattern: "/seller/listings/:id(.:format)", at: moment("2026-08-22 10:00:00"))
    PageViewCount.record!(path_pattern: "/admin(.:format)", at: moment("2026-08-22 10:00:00"))

    assert_equal "shop", PageViewCount.find_by(path_pattern: "/art/:slug").site
    assert_equal "seller", PageViewCount.find_by(path_pattern: "/seller/listings/:id(.:format)").site
    assert_equal "admin", PageViewCount.find_by(path_pattern: "/admin(.:format)").site
  end

  test "one hit costs one statement" do
    queries = count_queries { PageViewCount.record!(path_pattern: "/art/:slug", at: moment("2026-08-22 10:00:00")) }

    assert_equal 1, queries
  end

  test "two different patterns on the same day and site keep separate rows" do
    PageViewCount.record!(path_pattern: "/art/:slug", at: moment("2026-08-22 10:00:00"))
    PageViewCount.record!(path_pattern: "/", at: moment("2026-08-22 10:00:00"))

    assert_equal 2, PageViewCount.count
  end

  test "by_day sums every pattern into one row per day, newest first" do
    PageViewCount.record!(path_pattern: "/art/:slug", at: moment("2026-08-21 10:00:00"))
    PageViewCount.record!(path_pattern: "/", at: moment("2026-08-21 11:00:00"))
    PageViewCount.record!(path_pattern: "/art/:slug", at: moment("2026-08-22 10:00:00"))

    assert_equal [ [ Date.new(2026, 8, 22), 1 ], [ Date.new(2026, 8, 21), 2 ] ], PageViewCount.by_day
  end

  test "by_pattern sums every day into one row per pattern, busiest first" do
    PageViewCount.record!(path_pattern: "/art/:slug", at: moment("2026-08-21 10:00:00"))
    PageViewCount.record!(path_pattern: "/art/:slug", at: moment("2026-08-22 10:00:00"))
    PageViewCount.record!(path_pattern: "/", at: moment("2026-08-22 10:00:00"))

    assert_equal [ [ [ "shop", "/art/:slug" ], 2 ], [ [ "shop", "/" ], 1 ] ], PageViewCount.by_pattern
  end

  test "total_for folds a run of days in one statement" do
    PageViewCount.record!(path_pattern: "/art/:slug", at: moment("2026-08-21 10:00:00"))
    PageViewCount.record!(path_pattern: "/art/:slug", at: moment("2026-08-22 10:00:00"))
    PageViewCount.record!(path_pattern: "/art/:slug", at: moment("2026-08-30 10:00:00"))

    assert_equal 2, PageViewCount.total_for(Date.new(2026, 8, 18)..Date.new(2026, 8, 24))
  end

  test "total_for a window with no traffic is zero" do
    assert_equal 0, PageViewCount.total_for(Date.new(2026, 8, 18)..Date.new(2026, 8, 24))
  end
end

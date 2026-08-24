require "test_helper"

class Admin::StatsControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor is sent to the sign-in page" do
    get admin_stats_path

    assert_redirected_to admin_login_path
  end

  test "it says so where no traffic and no activity are recorded" do
    sign_in_as_admin

    get admin_stats_path

    assert_select "[data-empty=?]", "views_by_day"
    assert_select "[data-empty=?]", "views_by_pattern"
  end

  test "every listing event type is tallied, even at zero" do
    sign_in_as_admin
    listing = create_listing
    listing.record_event!("view", at: moment("2026-08-20 08:00:00"))

    get admin_stats_path

    assert_select "[data-stat=event-view] dd", text: "1"
    assert_select "[data-stat=event-favorite] dd", text: "0"
    assert_select "[data-stat=event-unfavorite] dd", text: "0"
    assert_select "[data-stat=event-cart_add] dd", text: "0"
  end

  test "page views by day lists a row per day the traffic table holds" do
    sign_in_as_admin
    PageViewCount.record!(path_pattern: "/art/:slug", at: moment("2026-08-20 09:00:00"))
    PageViewCount.record!(path_pattern: "/", at: moment("2026-08-20 10:00:00"))
    PageViewCount.record!(path_pattern: "/art/:slug", at: moment("2026-08-21 09:00:00"))

    get admin_stats_path

    assert_select "[data-day=?] [data-cell=count]", "2026-08-21", text: "1"
    assert_select "[data-day=?] [data-cell=count]", "2026-08-20", text: "2"
  end

  test "page views by pattern lists a row per route pattern" do
    sign_in_as_admin
    PageViewCount.record!(path_pattern: "/art/:slug", at: moment("2026-08-20 09:00:00"))
    PageViewCount.record!(path_pattern: "/art/:slug", at: moment("2026-08-21 09:00:00"))
    PageViewCount.record!(path_pattern: "/", at: moment("2026-08-20 10:00:00"))

    get admin_stats_path

    assert_select "[data-pattern=?] [data-cell=count]", "/art/:slug", text: "2"
    assert_select "[data-pattern=?] [data-cell=count]", "/", text: "1"
  end
end

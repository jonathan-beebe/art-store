require "test_helper"

class DebugAlertTest < ActionView::TestCase
  test "renders the magic link the delivery port flashed" do
    controller.flash[:debug_magic_link] = "http://localhost:3000/auth/verify/token"

    render partial: "layouts/debug_alert"

    assert_select "[role=alert] a[href=?]", "http://localhost:3000/auth/verify/token"
  end

  test "renders nothing when no magic link was flashed" do
    render partial: "layouts/debug_alert"

    assert_equal "", rendered.strip
  end
end

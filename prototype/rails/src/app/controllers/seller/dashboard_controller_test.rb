require "test_helper"

class Seller::DashboardControllerTest < ActionDispatch::IntegrationTest
  test "the dashboard renders in the seller layout" do
    get seller_root_path

    assert_response :success
    assert_select "body[data-site=?]", "seller"
    assert_select "h1"
  end

  test "the dashboard links the built Tailwind stylesheet" do
    get seller_root_path

    assert_select "head link[rel=stylesheet][href*=?]", "tailwind"
  end
end

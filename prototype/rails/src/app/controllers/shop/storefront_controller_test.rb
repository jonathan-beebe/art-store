require "test_helper"

module Shop
  class StorefrontControllerTest < ActionDispatch::IntegrationTest
    test "the storefront renders in the shop layout" do
      get root_path

      assert_response :success
      assert_select "body[data-site=?]", "shop"
      assert_select "h1"
    end

    test "the storefront links the built Tailwind stylesheet" do
      get root_path

      assert_select "head link[rel=stylesheet][href*=?]", "tailwind"
    end
  end
end

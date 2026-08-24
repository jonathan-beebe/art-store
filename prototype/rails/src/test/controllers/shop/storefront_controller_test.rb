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

    test "it shows a for-sale listing with its artist and price" do
      create_listing(create_seller(shop_name: "Blue Kiln Studio"), title: "Harbour at Dusk")

      get root_path

      assert_select "h2", text: "Harbour at Dusk"
      assert_select "p", text: "Blue Kiln Studio"
      assert_select "p", text: "$450.00"
    end

    test "it leaves out listings that are not for sale" do
      create_listing(title: "Still Draft", status: "draft")
      create_listing(title: "Already Sold", status: "sold", quantity: 0)
      create_listing(title: "Harbour at Dusk")

      get root_path

      assert_select "h2", text: "Harbour at Dusk"
      assert_select "h2", text: "Still Draft", count: 0
      assert_select "h2", text: "Already Sold", count: 0
    end

    test "a blocked customer can still browse" do
      create_listing(title: "Harbour at Dusk")
      sign_in_as_customer
      visiting_customer.block!(reason: "Chargeback fraud.", by: create_admin)

      get root_path

      assert_response :success
      assert_select "h2", text: "Harbour at Dusk"
    end

    test "it leaves out a listing an admin removed" do
      removed = create_listing(title: "Under Review")
      removed.remove!(kind: :temporary, reason: "Reported.", by: create_admin)
      create_listing(title: "Harbour at Dusk")

      get root_path

      assert_select "h2", text: "Harbour at Dusk"
      assert_select "h2", text: "Under Review", count: 0
    end

    test "it searches titles, descriptions, and media" do
      create_listing(title: "Harbour at Dusk", description: "Boats", medium: "Oil on canvas")
      create_listing(title: "Kiln Fired", description: "A dusk-lit vessel", medium: "Ceramic")
      create_listing(title: "Winter Field", description: "Snow", medium: "Watercolour")

      get root_path(q: "dusk")

      assert_select "h2", text: "Harbour at Dusk"
      assert_select "h2", text: "Kiln Fired"
      assert_select "h2", text: "Winter Field", count: 0
      assert_select "h1", text: /dusk/
    end

    test "it narrows to one medium" do
      create_listing(title: "Harbour at Dusk", medium: "Oil on canvas")
      create_listing(title: "Kiln Fired", medium: "Ceramic")

      get root_path(medium: "Ceramic")

      assert_select "h2", text: "Kiln Fired"
      assert_select "h2", text: "Harbour at Dusk", count: 0
    end

    test "the search and the medium filter narrow together" do
      create_listing(title: "Harbour at Dusk", medium: "Oil on canvas")
      create_listing(title: "Harbour Ceramic", medium: "Ceramic")

      get root_path(q: "harbour", medium: "Ceramic")

      assert_select "h2", text: "Harbour Ceramic"
      assert_select "h2", text: "Harbour at Dusk", count: 0
    end

    test "it offers the media of listings that are for sale" do
      create_listing(medium: "Ceramic")
      create_listing(medium: "Watercolour", status: "draft")

      get root_path

      assert_select "select[name=medium] option", text: "Ceramic"
      assert_select "select[name=medium] option", text: "Watercolour", count: 0
    end

    test "it paginates at twelve listings" do
      artist = create_seller
      14.times { |index| create_listing(artist, title: "Study #{index}") }

      get root_path

      assert_select "ul li", count: 12
      assert_select "a[rel=next]", text: "Next"

      get root_path(page: 2)

      assert_select "ul li", count: 2
      assert_select "a[rel=prev]", text: "Previous"
    end

    test "the feed orders listings by creation time, not by mint order" do
      seller = create_seller
      minted_first = create_listing(seller, title: "Minted First", created_at: 1.day.ago)
      create_listing(seller, title: "Minted Second", created_at: 5.days.ago)

      get root_path

      assert_select "ul li:first-child h2", text: minted_first.title
      assert_select "ul li:last-child h2", text: "Minted Second"
    end

    test "a page past the end lands on the last page" do
      create_listing(title: "Harbour at Dusk")

      get root_path(page: 99)

      assert_select "h2", text: "Harbour at Dusk"
    end

    test "an empty catalogue says so" do
      get root_path

      assert_select "p", text: "No art matches that yet."
    end

    test "the header carries the storefront links" do
      get root_path

      assert_select "nav a", text: "Favorites"
      assert_select "nav a", text: "Cart (0)"
      assert_select "nav a", text: "Orders"
      assert_select "nav a", text: "Sign in"
    end
  end
end

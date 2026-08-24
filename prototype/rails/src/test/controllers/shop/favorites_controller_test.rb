require "test_helper"

module Shop
  class FavoritesControllerTest < ActionDispatch::IntegrationTest
    test "it saves a favorite and records the event" do
      listing = create_listing

      post shop_toggle_favorite_path(slug: listing.slug)

      assert_response :redirect
      assert visiting_customer.favorites.exists?(listing: listing)
      assert_equal "favorite", listing.events.sole.event_type
    end

    test "a blocked customer can still favorite" do
      listing = create_listing
      sign_in_as_customer
      visiting_customer.block!(reason: "Chargeback fraud.", by: create_admin)

      post shop_toggle_favorite_path(slug: listing.slug)

      assert_response :redirect
      assert visiting_customer.favorites.exists?(listing: listing)
    end

    test "favoriting twice drops the favorite and records the event" do
      listing = create_listing
      post shop_toggle_favorite_path(slug: listing.slug)

      post shop_toggle_favorite_path(slug: listing.slug)

      refute visiting_customer.favorites.exists?(listing: listing)
      assert_equal %w[favorite unfavorite], listing.events.order(:occurred_at).pluck(:event_type)
    end

    test "it returns the visitor to the page they favorited from" do
      listing = create_listing

      post shop_toggle_favorite_path(slug: listing.slug), headers: { "HTTP_REFERER" => shop_favorites_path }

      assert_redirected_to shop_favorites_path
    end

    test "a listing that was never public cannot be favorited" do
      listing = create_listing(status: "draft")

      post shop_toggle_favorite_path(slug: listing.slug)

      assert_response :not_found
    end

    test "it lists what the visitor saved" do
      saved = create_listing(title: "Harbour at Dusk")
      ignored = create_listing(title: "Winter Field")
      post shop_toggle_favorite_path(slug: saved.slug)

      get shop_favorites_path

      assert_response :success
      assert_select "h2", text: "Harbour at Dusk"
      assert_select "h2", text: "Winter Field", count: 0
      assert_nil ignored.reload.favorites.first
    end

    test "an empty favorites page says so" do
      get shop_favorites_path

      assert_select "p", text: /Nothing saved yet/
    end

    test "the listing page offers to remove a favorite the visitor saved" do
      listing = create_listing
      post shop_toggle_favorite_path(slug: listing.slug)

      get shop_listing_path(slug: listing.slug)

      assert_select "button", text: "Remove from favorites"
    end

    test "favorites saved before signing in survive the merge" do
      listing = create_listing
      create_verified_customer(email: "buyer@example.com")
      post shop_toggle_favorite_path(slug: listing.slug)
      guest = visiting_customer

      sign_in_as_customer(email: "buyer@example.com")

      get shop_favorites_path
      assert_select "h2", text: listing.title
      refute_equal guest.id, visiting_customer.id
      assert visiting_customer.favorites.exists?(listing: listing)
    end
  end
end

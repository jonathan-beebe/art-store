require "test_helper"

class SellerTest < ActiveSupport::TestCase
  test "the address is normalized on the way in" do
    seller = create_seller(email: "  Artist@Example.COM ")

    assert_equal "artist@example.com", seller.email
  end

  test "two sellers cannot hold the same address" do
    create_seller(email: "artist@example.com")

    assert_raises(ActiveRecord::RecordNotUnique) { create_seller(email: "ARTIST@example.com") }
  end

  test "a first link for an address creates the seller" do
    seller = Seller.claim("newcomer@example.com")

    assert_equal "newcomer@example.com", seller.email
    assert_equal 1, Seller.count
  end

  test "a first link marks the address verified" do
    freeze_time do
      assert_equal Time.current, Seller.claim("newcomer@example.com").email_verified_at
    end
  end

  test "a later link returns the seller already holding the address" do
    existing = create_seller

    assert_equal existing, Seller.claim(existing.email)
    assert_equal 1, Seller.count
  end

  test "a later link leaves the original verification time alone" do
    existing = create_seller(email_verified_at: 3.days.ago)

    assert_equal existing.email_verified_at.to_i, Seller.claim(existing.email).email_verified_at.to_i
  end

  test "an address differing only in case reaches the same seller" do
    existing = create_seller(email: "artist@example.com")

    assert_equal existing, Seller.claim("ARTIST@Example.com")
  end

  test "a named shop is displayed under its name" do
    assert_equal "Blue Kiln Studio", create_seller(shop_name: "Blue Kiln Studio").display_name
  end

  test "an unnamed shop is displayed under the local part of the address" do
    assert_equal "ada", create_seller(shop_name: nil, email: "ada@example.test").display_name
  end

  test "a shop named with whitespace counts as unnamed" do
    assert_equal "ada", create_seller(shop_name: "   ", email: "ada@example.test").display_name
  end

  test "the status counts cover every status in lifecycle order" do
    assert_equal %w[draft for_sale sold archived], create_seller.listing_status_counts.map(&:first)
  end

  test "the status counts read the listings the seller owns" do
    shop = create_seller
    create_listing(shop, status: :for_sale)
    create_listing(shop, status: :for_sale)
    create_listing(create_seller, status: :sold, quantity: 0)

    assert_equal [["draft", 0], ["for_sale", 2], ["sold", 0], ["archived", 0]], shop.listing_status_counts
  end

  test "a seller reads their escrow balance off their own ledger" do
    shop = create_seller
    other = create_seller
    paid_order_for(create_verified_customer, create_listing(shop))

    assert_equal 40_500, shop.escrow_balance.held.cents
    assert_equal 0, other.escrow_balance.held.cents
  end

  test "a seller counts the unread messages across their own threads" do
    shop = create_seller
    buyer = create_verified_customer
    listing = create_listing(shop)
    conversation = Conversation.open(kind: :listing_question, seller: shop, customer: buyer, subject: listing)
    conversation.post!(buyer, "Is the frame included?")
    conversation.post!(shop, "It is.")

    assert_equal 1, shop.unread_message_count
    assert_equal 0, create_seller.unread_message_count
  end
end

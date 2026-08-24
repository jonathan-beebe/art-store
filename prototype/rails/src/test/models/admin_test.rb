require "test_helper"

class AdminTest < ActiveSupport::TestCase
  test "the address is normalized on the way in" do
    admin = create_admin(email: "  Ops@Example.COM ")

    assert_equal "ops@example.com", admin.email
  end

  test "two admins cannot hold the same address" do
    create_admin(email: "ops@example.com")

    assert_raises(ActiveRecord::RecordNotUnique) { create_admin(email: "OPS@example.com") }
  end

  test "a link for a seeded address reaches the admin holding it" do
    seeded = create_admin

    assert_equal seeded, Admin.claim(seeded.email)
  end

  test "an address differing only in case reaches the same admin" do
    seeded = create_admin(email: "ops@example.com")

    assert_equal seeded, Admin.claim("OPS@Example.com")
  end

  test "a link for an address no admin holds claims nothing" do
    create_admin(email: "ops@example.com")

    assert_nil Admin.claim("stranger@example.com")
  end

  test "a link never creates an admin" do
    Admin.claim("stranger@example.com")

    assert_equal 0, Admin.count
  end

  test "the desk a support thread opens against is the first admin by id" do
    desk = create_admin(email: "ops@example.com")
    create_admin(email: "later@example.com")

    assert_equal desk, Admin.on_duty
  end

  test "with no admin row nobody is on the desk" do
    assert_nil Admin.on_duty
  end

  test "an operator reads as their name, and as their address while they have none" do
    assert_equal "Ops", create_admin(name: "Ops").display_name
    assert_equal "ops", create_admin(name: " ", email: "ops@example.com").display_name
  end

  test "an admin holds the notifications filed under them" do
    admin = create_admin
    notification = Notification.create!(recipient: admin, subject: "Support", body: "A seller wrote in.")

    assert_equal [ notification ], admin.notifications.to_a
  end

  test "an admin counts the unread messages across their own threads" do
    admin = create_admin
    shop = create_seller
    Conversation.open(kind: :admin_seller, admin: admin, seller: shop).post!(shop, "My payout is late.")

    assert_equal 1, admin.unread_message_count
    assert_equal 0, create_admin.unread_message_count
  end
end

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

  test "an admin holds the notifications filed under them" do
    admin = create_admin
    notification = Notification.create!(recipient: admin, subject: "Support", body: "A seller wrote in.")

    assert_equal [notification], admin.notifications.to_a
  end
end

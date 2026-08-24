require "test_helper"
require "rake"

class OrdersTaskTest < ActiveSupport::TestCase
  setup do
    Rails.application.load_tasks unless Rake::Task.task_defined?("orders:sweep")
    Rake::Task["orders:sweep"].reenable
  end

  test "it cancels the orders left unverified past the cutoff" do
    listing = create_listing(quantity: 1)
    order = guest_order(at: "2026-08-20 09:00:00", listing: listing)

    output = run_task("2026-08-24 09:00:00")

    assert_includes output, "Cancelling orders placed before 2026-08-23T09:00:00Z"
    assert_includes output, order.id
    assert_includes output, "1 order(s) cancelled."
    assert_predicate order.reload, :cancelled?
    assert_equal "for_sale", listing.reload.status
  end

  test "it says so when nothing has been sitting that long" do
    guest_order(at: "2026-08-24 08:00:00")

    output = run_task("2026-08-24 09:00:00")

    assert_includes output, "No order has been sitting unverified that long."
    assert_equal 0, Order.cancelled.count
  end

  private

  def run_task(as_of)
    capture_io { Rake::Task["orders:sweep"].invoke(as_of) }.first
  end

  def guest_order(at:, listing: create_listing)
    guest = create_anonymous_customer

    Order.place(
      cart: cart_holding(guest, listing), customer: guest, email: "guest@example.test",
      shipping: shipping_address, at: moment(at)
    )
  end
end

require "test_helper"

class SeedsTest < ActiveSupport::TestCase
  setup { Rails.application.load_seed }

  test "it seeds two verified admins with Jonathan on duty" do
    admins = Admin.order(:id)

    assert_equal [ "jonathan-beebe@outlook.com", "annaschmunk@pm.me" ], admins.map(&:email)
    assert_equal [ "Jonathan Beebe", "Anna Schmunk" ], admins.map(&:name)
    assert_equal "jonathan-beebe@outlook.com", Admin.on_duty.email
    assert admins.all? { |admin| admin.email_verified_at.present? }
  end

  test "it seeds four verified sellers" do
    assert_equal 4, Seller.count
    assert_equal 4, Seller.where.not(email_verified_at: nil).count
  end

  test "it seeds listings across statuses and media" do
    for_sale = Listing.where(status: "for_sale")

    assert_equal 24, for_sale.count
    assert_equal 3, Listing.where(status: "draft").count
    assert_equal 2, Listing.where(status: "sold").count
    assert_equal %w[ceramic painting photography print sculpture textile], for_sale.distinct.order(:medium).pluck(:medium)
  end

  test "it seeds one verified customer with favorites and view history" do
    customer = Customer.find_by(email: "casey@example.com")

    assert customer.present?
    assert customer.email_verified_at.present?
    assert_equal 3, Favorite.where(customer_id: customer.id).count
    assert_equal 12, ListingEvent.count
  end

  test "it seeds order history for two sellers" do
    assert_equal 3, Order.count
    assert_equal 0, Order.where(status: "pending_verification").count

    assert_equal 1, Fulfillment.awaiting_shipment.count
    assert_equal 1, Fulfillment.shipped.count
    assert_equal 1, Fulfillment.delivered.count
    assert_equal 2, Fulfillment.distinct.count(:seller_id)

    assert_equal 3, Payment.count
  end

  test "it releases and pays out the delivered order" do
    assert_equal 3, LedgerEntry.held.count
    assert_equal 1, LedgerEntry.released.count
    assert_equal 1, LedgerEntry.paid_out.count

    payout = Payout.sole
    delivered_fulfillment = Fulfillment.delivered.sole

    assert_equal delivered_fulfillment.seller_id, payout.seller_id
    assert_equal delivered_fulfillment.net_cents, payout.amount_cents
  end

  test "it seeds one thread of every kind, each ending unread" do
    assert_equal %w[admin_customer admin_seller fulfillment listing_question],
      Conversation.distinct.order(:kind).pluck(:kind)
    assert_equal 4, Conversation.count
    assert_equal 9, Message.count

    Conversation.find_each do |conversation|
      reader = conversation.counterpart_of(conversation.messages.oldest_first.last.sender)

      assert_equal 1, conversation.unread_count_for(reader), "#{conversation.kind} has nothing waiting"
    end
  end

  test "it seeds the fulfillment thread against the shipped order" do
    conversation = Conversation.fulfillment.sole

    assert_equal Fulfillment.shipped.sole, conversation.subject
    assert_equal conversation.subject.seller, conversation.seller
  end

  test "it publishes one answer from a listing question" do
    faq = ListingFaq.sole
    conversation = Conversation.listing_question.sole

    assert_equal conversation.subject, faq.listing
    assert_equal conversation.latest_message_from(conversation.customer).body, faq.question
    assert_equal conversation.latest_message_from(conversation.seller), faq.source_message
  end

  test "it notifies sellers and the customer" do
    assert_equal 14, Notification.count
    assert_equal 9, Notification.where(subject: "New message").count
  end

  test "it does not duplicate data on a second run" do
    Rails.application.load_seed

    assert_equal 4, Seller.count
  end
end

require "test_helper"

class ListingFaqTest < ActiveSupport::TestCase
  test "publishing puts the answer on the listing" do
    listing = create_listing

    faq = ListingFaq.publish(listing, question: "Is the frame included?", answer: "It is.")

    assert_equal [faq], listing.faqs.to_a
    assert_equal "Is the frame included?", faq.question
    assert_equal "It is.", faq.answer
  end

  test "an entry published from a thread records the answer it came from" do
    shop = create_seller
    listing = create_listing(shop)
    buyer = create_verified_customer
    conversation = Conversation.open(kind: :listing_question, seller: shop, customer: buyer, subject: listing)
    conversation.post!(buyer, "Is the frame included?")
    answer = conversation.post!(shop, "It is.")

    faq = ListingFaq.publish(listing, question: "Is the frame included?", answer: "It is.", source_message: answer)

    assert_equal answer, faq.source_message
  end

  test "publishing stamps the moment it went up" do
    faq = ListingFaq.publish(
      create_listing, question: "Is the frame included?", answer: "It is.", at: moment("2026-08-22 09:00:00")
    )

    assert_equal moment("2026-08-22 09:00:00"), faq.published_at
  end

  test "an entry outlives the thread it was lifted from" do
    shop = create_seller
    listing = create_listing(shop)
    buyer = create_verified_customer
    conversation = Conversation.open(kind: :listing_question, seller: shop, customer: buyer, subject: listing)
    faq = ListingFaq.publish(
      listing, question: "Is the frame included?", answer: "It is.",
      source_message: conversation.post!(shop, "It is.")
    )

    conversation.destroy!

    assert_nil faq.reload.source_message_id
  end

  test "an edited entry keeps its place on the listing" do
    faq = ListingFaq.publish(create_listing, question: "Is the frame included?", answer: "It is.")

    faq.update!(answer: "It is, in a maple float frame.")

    assert_equal "It is, in a maple float frame.", faq.reload.answer
  end

  test "unpublishing takes the entry off the listing" do
    listing = create_listing
    faq = ListingFaq.publish(listing, question: "Is the frame included?", answer: "It is.")

    faq.destroy!

    assert_empty listing.faqs.reload
  end

  test "an entry with no question or no answer is invalid" do
    listing = create_listing

    assert_predicate ListingFaq.new(listing: listing, question: " ", answer: "It is."), :invalid?
    assert_predicate ListingFaq.new(listing: listing, question: "Is the frame included?", answer: " "), :invalid?
  end

  test "a question over 500 characters or an answer over 2000 is invalid" do
    listing = create_listing

    assert_predicate ListingFaq.new(listing: listing, question: "a" * 500, answer: "b" * 2_000), :valid?
    assert_predicate ListingFaq.new(listing: listing, question: "a" * 501, answer: "b"), :invalid?
    assert_predicate ListingFaq.new(listing: listing, question: "a", answer: "b" * 2_001), :invalid?
  end

  test "entries read in the order they were published" do
    listing = create_listing
    first = ListingFaq.publish(listing, question: "Is the frame included?", answer: "It is.")
    second = ListingFaq.publish(listing, question: "Does it ship abroad?", answer: "It does.")

    assert_equal [first, second], listing.faqs.oldest_first.to_a
  end

  test "a draft from a thread pairs the last question with the last answer" do
    conversation = answered_question
    buyer = conversation.customer
    conversation.post!(buyer, "What wood is the frame?", at: moment("2026-08-21 11:00:00"))
    answer = conversation.post!(conversation.seller, "Maple.", at: moment("2026-08-21 12:00:00"))

    draft = ListingFaq.draft_from(conversation)

    assert_equal "What wood is the frame?", draft.question
    assert_equal "Maple.", draft.answer
    assert_equal answer, draft.source_message
    assert_equal conversation.subject, draft.listing
    assert_predicate draft, :new_record?
  end

  test "a thread the seller has not answered drafts nothing" do
    shop = create_seller
    buyer = create_verified_customer
    conversation = Conversation.open(
      kind: :listing_question, seller: shop, customer: buyer, subject: create_listing(shop)
    )
    conversation.post!(buyer, "Is the frame included?")

    assert_nil ListingFaq.draft_from(conversation)
  end

  test "a thread that is not a question on a listing drafts nothing" do
    conversation = Conversation.open(kind: :admin_seller, admin: create_admin, seller: create_seller)

    assert_nil ListingFaq.draft_from(conversation)
  end

  test "a listing carries its entries away with it" do
    listing = create_listing
    ListingFaq.publish(listing, question: "Is the frame included?", answer: "It is.")

    listing.destroy!

    assert_equal 0, ListingFaq.count
  end

  private

  # A question a customer asked on a listing and the seller answered.
  def answered_question
    shop = create_seller
    buyer = create_verified_customer
    conversation = Conversation.open(
      kind: :listing_question, seller: shop, customer: buyer, subject: create_listing(shop)
    )
    conversation.post!(buyer, "Is the frame included?", at: moment("2026-08-21 09:00:00"))
    conversation.post!(shop, "It is.", at: moment("2026-08-21 10:00:00"))

    conversation
  end
end

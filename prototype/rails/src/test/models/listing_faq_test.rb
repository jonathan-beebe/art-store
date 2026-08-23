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

  test "a listing carries its entries away with it" do
    listing = create_listing
    ListingFaq.publish(listing, question: "Is the frame included?", answer: "It is.")

    listing.destroy!

    assert_equal 0, ListingFaq.count
  end
end

require "test_helper"

class Seller::FaqsControllerTest < ActionDispatch::IntegrationTest
  test "a signed-out visitor reaches no FAQ page" do
    listing = create_listing

    get seller_listing_faqs_path(listing)

    assert_redirected_to seller_login_path
  end

  test "the page lists what is published with a form for each entry" do
    seller = signed_in_seller
    listing = create_listing(seller)
    faq = ListingFaq.publish(listing, question: "Is the frame included?", answer: "It is.")

    get seller_listing_faqs_path(listing)

    assert_response :success
    assert_select "[data-faq=?] textarea[name=?]", faq.id.to_s, "listing_faq[question]",
      text: /Is the frame included\?/
    assert_select "[data-faq=?] form[action=?][method=post]", faq.id.to_s,
      seller_listing_faq_path(listing, faq)
    assert_select "[data-faq=?] button", faq.id.to_s, text: "Unpublish"
  end

  test "a listing with nothing published says so" do
    seller = signed_in_seller

    get seller_listing_faqs_path(create_listing(seller))

    assert_select "p", text: "Nothing published yet."
  end

  test "the page publishes an entry written from scratch" do
    seller = signed_in_seller
    listing = create_listing(seller)

    post seller_listing_faqs_path(listing),
      params: { listing_faq: { question: "Does it ship abroad?", answer: "It does." } }

    faq = listing.faqs.sole
    assert_redirected_to seller_listing_faqs_path(listing)
    assert_equal "Published to the listing.", flash[:notice]
    assert_equal "Does it ship abroad?", faq.question
    assert_nil faq.source_message
  end

  test "an entry with no question comes back with the field error" do
    seller = signed_in_seller
    listing = create_listing(seller)

    post seller_listing_faqs_path(listing), params: { listing_faq: { question: " ", answer: "It does." } }

    assert_response :unprocessable_content
    assert_select "[data-field-error=?]", "listing_faq_question", text: "Enter the question."
    assert_empty listing.faqs
  end

  test "a question over 500 characters comes back with the field error" do
    seller = signed_in_seller
    listing = create_listing(seller)

    post seller_listing_faqs_path(listing),
      params: { listing_faq: { question: "a" * 501, answer: "It does." } }

    assert_response :unprocessable_content
    assert_select "[data-field-error=?]", "listing_faq_question",
      text: "Keep the question under 500 characters."
  end

  test "an answer over 2000 characters comes back with the field error" do
    seller = signed_in_seller
    listing = create_listing(seller)

    post seller_listing_faqs_path(listing),
      params: { listing_faq: { question: "Does it ship abroad?", answer: "b" * 2_001 } }

    assert_response :unprocessable_content
    assert_select "[data-field-error=?]", "listing_faq_answer",
      text: "Keep the answer under 2000 characters."
  end

  test "an entry published from a thread records the answer it came from" do
    seller = signed_in_seller
    listing = create_listing(seller)
    answer = answered_question(seller, listing).latest_message_from(seller)

    post seller_listing_faqs_path(listing), params: {
      listing_faq: { question: "Is the frame included?", answer: "It is.", source_message_id: answer.id }
    }

    assert_equal answer, listing.faqs.sole.source_message
  end

  test "an answer from another listing's thread is not found" do
    seller = signed_in_seller
    listing = create_listing(seller)
    elsewhere = answered_question(seller, create_listing(seller)).latest_message_from(seller)

    post seller_listing_faqs_path(listing), params: {
      listing_faq: { question: "Is the frame included?", answer: "It is.", source_message_id: elsewhere.id }
    }

    assert_response :not_found
    assert_empty listing.faqs
  end

  test "an answer from another seller's thread is not found" do
    seller = signed_in_seller
    listing = create_listing(seller)
    rival = other_seller
    stranger = answered_question(rival, create_listing(rival)).latest_message_from(rival)

    post seller_listing_faqs_path(listing), params: {
      listing_faq: { question: "Is the frame included?", answer: "It is.", source_message_id: stranger.id }
    }

    assert_response :not_found
    assert_empty listing.faqs
  end

  test "the seller edits the text of a published entry" do
    seller = signed_in_seller
    listing = create_listing(seller)
    faq = ListingFaq.publish(listing, question: "Is the frame included?", answer: "It is.")

    patch seller_listing_faq_path(listing, faq),
      params: { listing_faq: { question: "Is the frame included?", answer: "It is, in maple." } }

    assert_redirected_to seller_listing_faqs_path(listing)
    assert_equal "The entry is updated.", flash[:notice]
    assert_equal "It is, in maple.", faq.reload.answer
  end

  test "a refused edit comes back holding the text the seller typed" do
    seller = signed_in_seller
    listing = create_listing(seller)
    faq = ListingFaq.publish(listing, question: "Is the frame included?", answer: "It is.")

    patch seller_listing_faq_path(listing, faq),
      params: { listing_faq: { question: "Does it ship abroad?", answer: " " } }

    assert_response :unprocessable_content
    assert_select "[data-field-error=?]", "faq_#{faq.id}_listing_faq_answer", text: "Enter the answer."
    assert_select "[data-faq=?] textarea[name=?]", faq.id.to_s, "listing_faq[question]",
      text: /Does it ship abroad\?/
    assert_equal "It is.", faq.reload.answer
  end

  test "unpublishing takes the entry off the listing" do
    seller = signed_in_seller
    listing = create_listing(seller)
    faq = ListingFaq.publish(listing, question: "Is the frame included?", answer: "It is.")

    delete seller_listing_faq_path(listing, faq)

    assert_redirected_to seller_listing_faqs_path(listing)
    assert_equal "Unpublished from the listing.", flash[:notice]
    assert_empty listing.faqs.reload
  end

  test "another seller's listing carries no FAQs of this seller's" do
    signed_in_seller

    get seller_listing_faqs_path(create_listing(other_seller))

    assert_response :not_found
  end

  test "the listing page links to its FAQs" do
    seller = signed_in_seller
    listing = create_listing(seller)

    get seller_listing_path(listing)

    assert_select "a[href=?]", seller_listing_faqs_path(listing), text: "FAQs"
  end

  private

  # A question a customer asked on one of this seller's listings, answered.
  def answered_question(seller, listing)
    conversation = Conversation.open(
      kind: :listing_question, seller: seller, customer: create_verified_customer, subject: listing
    )
    conversation.post!(conversation.customer, "Is the frame included?", at: moment("2026-08-21 09:00:00"))
    conversation.post!(seller, "It is.", at: moment("2026-08-21 10:00:00"))

    conversation
  end
end

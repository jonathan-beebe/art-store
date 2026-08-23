require "test_helper"

module Shop
  class ListingQuestionsControllerTest < ActionDispatch::IntegrationTest
    test "a question a visitor asks lands on the thread with the seller" do
      listing = create_listing(create_seller(shop_name: "Blue Kiln Studio"))

      post shop_listing_questions_path(slug: listing.slug), params: { message: { body: "Is the frame included?" } }

      conversation = Conversation.sole
      assert_redirected_to shop_conversation_path(conversation)
      assert_predicate conversation, :listing_question?
      assert_equal listing, conversation.subject
      assert_equal listing.seller, conversation.seller
      assert_equal visiting_customer, conversation.customer
      assert_equal "Is the frame included?", conversation.messages.sole.body
    end

    test "the visitor asking needs no address" do
      listing = create_listing

      post shop_listing_questions_path(slug: listing.slug), params: { message: { body: "Is the frame included?" } }

      assert_predicate visiting_customer, :anonymous?
      assert_response :redirect
    end

    test "the seller hears about a question" do
      listing = create_listing

      post shop_listing_questions_path(slug: listing.slug), params: { message: { body: "Is the frame included?" } }

      notification = listing.seller.notifications.sole
      assert_equal "New message", notification.subject
      assert_equal seller_conversation_path(Conversation.sole), notification.url
    end

    test "a second question reaches the thread already open" do
      listing = create_listing

      post shop_listing_questions_path(slug: listing.slug), params: { message: { body: "Is the frame included?" } }
      post shop_listing_questions_path(slug: listing.slug), params: { message: { body: "And the shipping?" } }

      assert_equal 1, Conversation.count
      assert_equal 2, Conversation.sole.messages.count
    end

    test "an empty question comes back to the listing and opens no thread" do
      listing = create_listing

      post shop_listing_questions_path(slug: listing.slug), params: { message: { body: "  " } }

      assert_redirected_to shop_listing_path(slug: listing.slug)
      assert_equal "Write a message.", flash[:alert]
      assert_empty Conversation.all
    end

    test "a question over 2000 characters is refused" do
      listing = create_listing

      post shop_listing_questions_path(slug: listing.slug), params: { message: { body: "a" * 2_001 } }

      assert_redirected_to shop_listing_path(slug: listing.slug)
      assert_equal "Keep the message under 2000 characters.", flash[:alert]
      assert_empty Message.all
    end

    test "a listing that is not on the storefront takes no questions" do
      listing = create_listing(status: "draft")

      post shop_listing_questions_path(slug: listing.slug), params: { message: { body: "Is the frame included?" } }

      assert_response :not_found
    end

    test "verifying an address already in use carries the thread over" do
      buyer = create_verified_customer(email: "buyer@example.com")
      listing = create_listing

      post shop_listing_questions_path(slug: listing.slug), params: { message: { body: "Is the frame included?" } }
      conversation = Conversation.sole
      asker = visiting_customer

      sign_in_as_customer(email: "buyer@example.com")

      assert_not_equal buyer, asker
      assert_equal buyer, conversation.reload.customer
      assert_equal buyer, conversation.messages.sole.sender
      get shop_conversation_path(conversation)
      assert_response :success
    end

    test "a question becomes an answer published on the listing" do
      seller = create_seller(shop_name: "Blue Kiln Studio")
      listing = create_listing(seller, title: "Harbour at Dusk")

      post shop_listing_questions_path(slug: listing.slug), params: { message: { body: "Is the frame included?" } }
      conversation = Conversation.sole
      assert_redirected_to shop_conversation_path(conversation)

      end_session
      sign_in_as(seller)
      get seller_conversations_path
      assert_select "[data-conversation=?] [data-cell=topic]", conversation.id.to_s, text: "“Harbour at Dusk”"

      post seller_conversation_messages_path(conversation),
        params: { message: { body: "It is, in a maple float frame." } }

      get seller_conversation_path(conversation)
      answer = conversation.latest_message_from(seller)
      assert_select "form[action=?] input[name=?][value=?]",
        seller_listing_faqs_path(listing), "listing_faq[source_message_id]", answer.id.to_s
      assert_select "textarea[name=?]", "listing_faq[question]", text: /Is the frame included\?/
      assert_select "textarea[name=?]", "listing_faq[answer]", text: /maple float frame/

      post seller_listing_faqs_path(listing), params: {
        listing_faq: {
          question: "Is the frame included?", answer: "It is, in a maple float frame.",
          source_message_id: answer.id
        }
      }
      assert_redirected_to seller_listing_faqs_path(listing)

      post customer_logout_path
      get shop_listing_path(slug: listing.slug)

      faq = listing.faqs.sole
      assert_equal answer, faq.source_message
      assert_select "[data-faq=?] dt", faq.id.to_s, text: "Is the frame included?"
      assert_select "[data-faq=?] dd", faq.id.to_s, text: "It is, in a maple float frame."
    end
  end
end

# One thread of each of the four kinds, and the one answer a seller published
# to a listing. Each thread ends on a message the other side has not opened, so
# a seeded inbox arrives with a badge on it.
module Seeds
  module Messaging
    module_function

    SUPPORT_SELLER_EMAIL = "maya@example.com"
    QUESTION_LISTING_TITLE = "Woodfired Vase, Tall"
    OPENED_AT = Time.utc(2026, 7, 12, 9, 0, 0)

    def create_all
      admin = Admin.on_duty
      customer = Customer.find_by!(email: Seeds::Customers::CASEY_EMAIL)

      support_for_seller(admin)
      support_for_customer(admin, customer)
      about_an_order(customer)
      publish(about_a_listing(customer))
    end

    def support_for_seller(admin)
      seller = Seller.find_by!(email: SUPPORT_SELLER_EMAIL)
      conversation = Conversation.open(kind: :admin_seller, admin: admin, seller: seller, at: OPENED_AT)

      say(conversation, seller,
        "My payout for the week of July 6 landed a day later than the one before it. " \
        "Is Monday the day the run happens?",
        at: OPENED_AT)
      answer(conversation, admin,
        "The run settles the week that closed on Sunday, and it goes out on the Monday after. " \
        "Yours is on that schedule.",
        at: OPENED_AT + 3.hours)
    end

    def support_for_customer(admin, customer)
      conversation = Conversation.open(kind: :admin_customer, admin: admin, customer: customer, at: OPENED_AT + 1.day)

      say(conversation, customer,
        "I confirmed delivery on the oak figure. Does the seller get paid straight away?",
        at: OPENED_AT + 1.day)
      answer(conversation, admin,
        "Confirming delivery releases the money to the seller, and it reaches them in that week's payout. " \
        "Thank you for closing it out.",
        at: OPENED_AT + 1.day + 2.hours)
    end

    # The thread hangs off the fulfillment the seed already shipped, which is
    # the slice of the order one seller owns.
    def about_an_order(customer)
      fulfillment = Fulfillment.shipped.sole
      conversation = Conversation.open(
        kind: :fulfillment, subject: fulfillment,
        seller: fulfillment.seller, customer: customer, at: OPENED_AT + 2.days
      )

      say(conversation, customer,
        "The tracking says the box is in Portland. Is a signature needed when it arrives?",
        at: OPENED_AT + 2.days)
      answer(conversation, fulfillment.seller,
        "No signature. The driver leaves it at the door, and the painting is crated inside the box.",
        at: OPENED_AT + 2.days + 90.minutes)
      answer(conversation, customer, "Perfect, thank you.", at: OPENED_AT + 2.days + 2.hours)
    end

    def about_a_listing(customer)
      listing = Listing.find_by!(title: QUESTION_LISTING_TITLE)
      conversation = Conversation.open(
        kind: :listing_question, subject: listing,
        seller: listing.seller, customer: customer, at: OPENED_AT + 3.days
      )

      say(conversation, customer,
        "Is the vase watertight, or does it want a liner for cut flowers?",
        at: OPENED_AT + 3.days)
      answer(conversation, listing.seller,
        "It holds water. The interior is glazed even though the outside is left raw from the wood firing, " \
        "so it takes a full arrangement with no liner.",
        at: OPENED_AT + 3.days + 4.hours)

      conversation
    end

    # What the seller's "Publish as FAQ" button does: the pair the thread
    # already holds, with the answer it came from.
    def publish(conversation, at: OPENED_AT + 3.days + 5.hours)
      draft = ListingFaq.draft_from(conversation)

      ListingFaq.publish(
        draft.listing,
        question: draft.question, answer: draft.answer, source_message: draft.source_message, at: at
      )
    end

    # A reply reads the thread it is replying in, so the side that answered
    # opens a clear inbox.
    def answer(conversation, sender, body, at:)
      conversation.read_by!(sender, at: at)
      say(conversation, sender, body, at: at)
    end

    # A message takes its time from the exchange rather than the clock, so a
    # seeded thread reads in the order it was written.
    def say(conversation, sender, body, at:)
      message = conversation.post!(sender, body, at: at)
      message.update_columns(created_at: at, updated_at: at)

      message
    end
  end
end

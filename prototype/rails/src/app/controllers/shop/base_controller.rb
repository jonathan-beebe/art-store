module Shop
  class BaseController < ApplicationController
    include CustomerAuthentication

    before_action :resolve_customer_identity

    layout "shop"

    # What a blocked customer is told when they try to buy. Browsing is
    # untouched, so the notice names only what the hold takes away.
    BLOCKED_SHOPPER_NOTICE = "Your account is on hold, so you cannot add to a cart or check out.".freeze

    private

    # Keeps a blocked customer off cart add, checkout, and pay, and says why.
    # The destination is per caller so the visitor lands back where they were.
    def refuse_blocked_customer(to:)
      return if current_customer.can_shop?

      redirect_to to, alert: blocked_shopper_notice
    end

    def blocked_shopper_notice
      [ BLOCKED_SHOPPER_NOTICE, current_customer.blocked_reason ].compact.join(" ")
    end

    # The storefront's visitor is always somebody, address or not.
    def logged_actor
      current_customer
    end

    # The account behind the request once the visitor has proved the address in
    # this session. A cookie alone leaves them a guest at checkout.
    def verified_account
      current_customer if customer_signed_in?
    end

    # Someone else's order is not theirs to read, pay, or receive, and "not
    # found" tells them nothing about whether it exists.
    def order_of_customer(id)
      current_customer.orders.find(id)
    end

    # Which side of a conversation the storefront's visitor sits on. A visitor
    # who has given no address still has threads, since a question about a
    # listing needs no account.
    def current_participant
      current_customer
    end
  end
end

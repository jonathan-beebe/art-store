module Shop
  class BaseController < ApplicationController
    include CustomerAuthentication

    before_action :resolve_customer_identity

    layout "shop"

    private

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

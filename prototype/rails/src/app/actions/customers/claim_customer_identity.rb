module Customers
  class ClaimCustomerIdentity
    Plan = Domain::Customers::IdentityPlan

    # +current+ is the customer the identity cookie points at, if any.
    # Returns the customer that now owns the address.
    def call(email:, current:, now: Time.current)
      address = Domain::Auth::EmailAddress.normalize(email)
      owner = Customer.find_by(email: address)
      plan = Plan.decide(
        anonymous_customer_id: current&.anonymous? ? current.id : nil,
        verified_customer_id: owner&.id
      )

      case plan.action
      when Plan::CREATE_VERIFIED then Customer.create!(email: address, email_verified_at: now)
      when Plan::SIGN_IN_EXISTING then verify(owner, now)
      when Plan::CLAIM_ANONYMOUS then claim(current, address, now)
      when Plan::MERGE_ANONYMOUS_INTO
        MergeAnonymousCustomer.new.call(anonymous: current, verified: verify(owner, now))
      end
    end

    private

    # A guest checkout can leave an address on a customer without verifying it;
    # clicking a link for that address settles it.
    def verify(customer, now)
      customer.update!(email_verified_at: now) if customer.email_verified_at.nil?

      customer
    end

    def claim(anonymous, address, now)
      anonymous.update!(email: address, email_verified_at: now)

      anonymous
    end
  end
end

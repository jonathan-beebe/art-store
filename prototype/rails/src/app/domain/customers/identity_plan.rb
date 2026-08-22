module Domain
  module Customers
    # What verifying an address does to the customer rows already in play: the
    # anonymous row the cookie points at, and the row that already holds the
    # address.
    class IdentityPlan < Data.define(:action, :anonymous_customer_id, :verified_customer_id)
      CREATE_VERIFIED = :create_verified
      SIGN_IN_EXISTING = :sign_in_existing
      CLAIM_ANONYMOUS = :claim_anonymous
      MERGE_ANONYMOUS_INTO = :merge_anonymous_into

      def self.decide(anonymous_customer_id:, verified_customer_id:)
        return sign_in_or_create(verified_customer_id) if anonymous_customer_id.nil?
        return claim(anonymous_customer_id) if verified_customer_id.nil?
        return sign_in_or_create(verified_customer_id) if anonymous_customer_id == verified_customer_id

        new(
          action: MERGE_ANONYMOUS_INTO,
          anonymous_customer_id: anonymous_customer_id,
          verified_customer_id: verified_customer_id
        )
      end

      def self.sign_in_or_create(verified_customer_id)
        new(
          action: verified_customer_id.nil? ? CREATE_VERIFIED : SIGN_IN_EXISTING,
          anonymous_customer_id: nil,
          verified_customer_id: verified_customer_id
        )
      end
      private_class_method :sign_in_or_create

      def self.claim(anonymous_customer_id)
        new(action: CLAIM_ANONYMOUS, anonymous_customer_id: anonymous_customer_id, verified_customer_id: nil)
      end
      private_class_method :claim

      # The customer the cookie and the session end on, or nil when that row
      # does not exist yet.
      def resulting_customer_id
        action == CLAIM_ANONYMOUS ? anonymous_customer_id : verified_customer_id
      end
    end
  end
end

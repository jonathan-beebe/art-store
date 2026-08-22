require_relative "../auth/email_address"
require_relative "../orders/purchaser"

module Domain
  module Shop
    module CheckoutPurchaser
      module_function

      # A signed-in customer buys under the address on their account, so a
      # submitted field cannot move an order onto someone else's identity. A
      # guest buys under the address they typed and verifies it afterwards.
      def for_checkout(id:, account_email:, account_verified_at:, submitted_email:)
        return Orders::Purchaser.new(id: id, email: account_email, email_verified_at: account_verified_at) if
          account_verified_at

        Orders::Purchaser.new(
          id: id, email: Auth::EmailAddress.normalize(submitted_email), email_verified_at: nil
        )
      end
    end
  end
end

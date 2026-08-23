require_relative "../orders/shipping_address"

module Domain
  module Shop
    # What checkout collects before an order can be opened: an address to reach
    # the buyer and an address to ship to.
    class CheckoutForm < Data.define(:email, :shipping)
      REQUIRED_PARTS = %i[name line1 city region postal_code country].freeze

      def self.from_input(email:, shipping:)
        new(
          email: email.to_s.strip,
          shipping: Orders::ShippingAddress.new(**Orders::ShippingAddress.members.to_h { |part|
            [part, filled(shipping[part])]
          })
        )
      end

      def self.filled(value)
        text = value.to_s.strip

        text.empty? ? nil : text
      end
      private_class_method :filled

      def complete?
        EmailAddress::SHAPE.match?(email) && missing_parts.empty?
      end

      def missing_parts
        REQUIRED_PARTS.reject { |part| shipping.public_send(part) }
      end
    end
  end
end

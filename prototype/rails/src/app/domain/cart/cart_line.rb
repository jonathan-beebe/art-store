require_relative "../money"

module Domain
  module Cart
    CartLine = Data.define(:seller_id, :unit_price, :quantity) do
      def initialize(seller_id:, unit_price:, quantity:)
        raise ArgumentError, "a cart line covers at least one item, got #{quantity}" if quantity < 1

        super
      end

      def total
        unit_price * quantity
      end
    end
  end
end

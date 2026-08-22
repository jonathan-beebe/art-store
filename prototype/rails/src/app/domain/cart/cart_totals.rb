require_relative "../money"
require_relative "cart_line"

module Domain
  module Cart
    # What a cart is worth, whole and split by the seller each line belongs to.
    CartTotals = Data.define(:item_count, :subtotal, :subtotals_by_seller) do
      def self.from(lines)
        new(
          item_count: lines.sum(&:quantity),
          subtotal: total_of(lines),
          subtotals_by_seller: lines.group_by(&:seller_id).transform_values { |own| total_of(own) }.sort.to_h.freeze
        )
      end

      def self.for_checkout(lines)
        raise ArgumentError, "an order needs at least one item" if lines.empty?

        from(lines)
      end

      def self.total_of(lines)
        lines.sum(Money.from_cents(0), &:total)
      end
      private_class_method :total_of
    end
  end
end

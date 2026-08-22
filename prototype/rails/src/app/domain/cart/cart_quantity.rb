module Domain
  module Cart
    module CartQuantity
      module_function

      # A cart never holds more of a listing than the seller has left.
      def within_stock(requested:, available:)
        raise ArgumentError, "a cart holds at least one of a listing, got #{requested}" if requested < 1
        raise ArgumentError, "that listing is sold out" if available < 1

        [requested, available].min
      end
    end
  end
end

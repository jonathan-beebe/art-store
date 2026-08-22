module Domain
  module Reports
    # Listings, orders, and fulfillments all store their status as a snake_case
    # string. A portal table prints it as a sentence.
    module StatusLabel
      module_function

      def of(status)
        status.to_s.tr("_", " ").sub(/\A[a-z]/, &:upcase)
      end
    end
  end
end

require_relative "order_status"
require_relative "../listings/stock_change"

module Domain
  module Orders
    # The stock an order holds. Placement claims it; a declined card hands it
    # back so the listing returns to the storefront, and a retry claims it again.
    module OrderStock
      RELEASED_BY = [OrderStatus::PAYMENT_FAILED, OrderStatus::CANCELLED].freeze

      module_function

      def holds?(status)
        !RELEASED_BY.include?(status)
      end

      def change(from:, to:)
        return Listings::StockChange::TAKE if !holds?(from) && holds?(to)
        return Listings::StockChange::RESTORE if holds?(from) && !holds?(to)

        Listings::StockChange::KEEP
      end
    end
  end
end

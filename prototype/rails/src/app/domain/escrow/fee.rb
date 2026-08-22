require_relative "../money"

module Domain
  module Escrow
    module Fee
      PLATFORM_PERCENT = 10

      module_function

      def platform(subtotal)
        subtotal.percent(PLATFORM_PERCENT)
      end

      def net(subtotal)
        Money.from_cents(subtotal.cents - platform(subtotal).cents)
      end
    end
  end
end

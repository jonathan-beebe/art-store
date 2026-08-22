module Domain
  module Escrow
    module LedgerEntryType
      HELD = "held"
      RELEASED = "released"
      PAID_OUT = "paid_out"

      ALL = [HELD, RELEASED, PAID_OUT].freeze
    end
  end
end

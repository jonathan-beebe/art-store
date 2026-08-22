module Domain
  module Orders
    # What a seller types on the mark-shipped form. A shipment the customer can
    # follow needs both parts, so the form is answered before the fulfillment
    # is asked to move.
    ShipmentDetails = Data.define(:carrier, :tracking_number) do
      def self.from_input(carrier:, tracking_number:)
        new(carrier: carrier.to_s.strip, tracking_number: tracking_number.to_s.strip)
      end

      def complete?
        !carrier.empty? && !tracking_number.empty?
      end
    end
  end
end

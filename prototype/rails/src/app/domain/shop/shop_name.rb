module Domain
  module Shop
    module ShopName
      module_function

      # A seller signs up with an address and names their shop later, so the
      # storefront falls back to the part of the address in front of the host.
      def of(shop_name:, email:)
        named = shop_name.to_s.strip

        named.empty? ? email.to_s.split("@").first.to_s : named
      end
    end
  end
end

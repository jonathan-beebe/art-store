module Domain
  module Listings
    # The storefront URL a listing is shared under. Titles repeat between
    # sellers, so a slug that is already taken counts up until it is free.
    module ListingSlug
      FALLBACK = "listing".freeze

      module_function

      def base(title)
        title.to_s.parameterize.presence || FALLBACK
      end

      def first_free(title, taken)
        candidate = base(title)
        return candidate unless taken.include?(candidate)

        suffix = 2
        suffix += 1 while taken.include?("#{candidate}-#{suffix}")

        "#{candidate}-#{suffix}"
      end
    end
  end
end

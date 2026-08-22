module Domain
  module Listings
    # The storefront URL a listing is shared under. Titles repeat between
    # sellers, so a slug that is already taken counts up until it is free.
    module ListingSlug
      FALLBACK = "listing".freeze

      module_function

      def base(title)
        slug = title.to_s.downcase.gsub(/[^a-z0-9]+/, "-").gsub(/\A-|-\z/, "")

        slug.empty? ? FALLBACK : slug
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

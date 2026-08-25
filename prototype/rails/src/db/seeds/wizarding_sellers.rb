# Two more verified sellers a reviewer can sign in as, each with a live
# catalog. Seeded separately from the demo data so they also land on a
# database the demo seed already refuses to touch. Refuses to run twice: a
# database that already holds the first seller's email is left untouched and
# answers nil.
module Seeds
  module WizardingSellers
    module_function

    VERIFIED_AT = Time.utc(2026, 8, 20)

    RECORDS = [
      {
        email: "neville@example.com", name: "Neville Longbottom", shop_name: "Longbottom Botanicals",
        listings: [
          { title: "Mimbulus Mimbletonia, Potted", medium: "plant",
            dimensions: "8 x 5 x 5 in", price_cents: 9_500, quantity: 1,
            description: "A rare grey cactus-like specimen, its surface moving gently as it breathes. " \
                          "Raised from a cutting my great uncle Algie brought back from Assyria. Ships in " \
                          "its own terracotta pot with a full care sheet — do not prod the boils." },
          { title: "Flitterbloom Cutting, Rooted", medium: "plant",
            dimensions: "12 in tendrils", price_cents: 4_500, quantity: 3,
            description: "A rooted Flitterbloom cutting with long swaying tendrils, often mistaken for " \
                          "Devil’s Snare but entirely harmless. Thrives in a bright window and asks for " \
                          "little beyond weekly water. Grown in Greenhouse Three from healthy parent stock." },
          { title: "Puffapod Seed Collection", medium: "plant",
            dimensions: "tin of 20 pods", price_cents: 2_500, quantity: 6,
            description: "Twenty plump pink Puffapod pods in a lidded tin. Drop one anywhere and it bursts " \
                          "into flower on the spot, so sow them where you mean it. Harvested by hand at " \
                          "full ripeness this season." },
          { title: "Bouncing Bulb, Established", medium: "plant",
            dimensions: "10 x 7 x 7 in", price_cents: 6_000, quantity: 1,
            description: "A well-established Bouncing Bulb, repotted twice and calm for its kind. Keeps to " \
                          "modest hops once it settles into a routine. Sturdy gloves recommended at " \
                          "repotting time; it only wriggles when startled." }
        ]
      },
      {
        email: "luna@example.com", name: "Luna Lovegood", shop_name: "Lovegood Curiosities",
        listings: [
          { title: "The Quibbler, Back-Issue Bundle", medium: "publication",
            dimensions: "8.5 x 11 in, set of 5", price_cents: 1_200, quantity: 12,
            description: "Five assorted back issues of The Quibbler, my father’s magazine, including the " \
                          "Crumple-Horned Snorkack expedition special. Some covers print upside down on " \
                          "purpose. Each bundle is different, which is rather the point." },
          { title: "Spectrespecs", medium: "curio",
            dimensions: "6 x 2 x 1 in", price_cents: 3_500, quantity: 5,
            description: "Pink-and-blue paper spectacles that make Wrackspurts visible as they drift out " \
                          "of people’s ears. Very useful for working out why your thinking has gone fuzzy. " \
                          "Free with some issues of The Quibbler, but these are the sturdier keepsake edition." },
          { title: "Butterbeer Cork Necklace", medium: "jewelry",
            dimensions: "18 in cord", price_cents: 1_800, quantity: 4,
            description: "A necklace of butterbeer corks strung on waxed cord, worn to keep the Nargles " \
                          "away. Each cork is collected personally and threaded by hand. The Nargles have " \
                          "never once bothered me while wearing it." },
          { title: "Dirigible Plum Earrings", medium: "jewelry",
            dimensions: "2 in drop", price_cents: 2_200, quantity: 3,
            description: "A pair of bright orange dirigible plum earrings, carved and painted to float " \
                          "just slightly on a breeze. The plums grow beside our front door and enhance the " \
                          "ability to accept the extraordinary. Hooks are plain silver." }
        ]
      }
    ].freeze

    def create_all
      return nil if Seller.exists?(email: RECORDS.first.fetch(:email))

      listing_count = 0

      RECORDS.each do |record|
        seller = Seller.create!(
          email: record.fetch(:email), name: record.fetch(:name),
          shop_name: record.fetch(:shop_name), email_verified_at: VERIFIED_AT
        )

        record.fetch(:listings).each do |listing|
          Listing.create!(
            seller_id: seller.id,
            title: listing.fetch(:title),
            slug: listing.fetch(:title).parameterize,
            description: listing.fetch(:description),
            price_cents: listing.fetch(:price_cents),
            quantity: listing.fetch(:quantity),
            status: "for_sale",
            medium: listing.fetch(:medium),
            dimensions: listing.fetch(:dimensions)
          )
          listing_count += 1
        end
      end

      { seller_count: RECORDS.length, listing_count: listing_count }
    end
  end
end

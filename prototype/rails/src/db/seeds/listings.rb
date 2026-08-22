# 24 for_sale listings across six media, three drafts, and two sold-out
# pieces. Three of the for_sale listings start at quantity 2 so
# Seeds::OrderHistory can sell one unit of each and leave them on the
# storefront. No image is attached — Listing#image_url falls back to
# PlaceholderImage.data_uri(title), which already differs per listing because
# it hashes the title.
module Seeds
  module Listings
    module_function

    FOR_SALE = Domain::Listings::ListingStatus::FOR_SALE
    DRAFT = Domain::Listings::ListingStatus::DRAFT
    SOLD = Domain::Listings::ListingStatus::SOLD

    RECORDS = [
      { seller_email: "maya@example.com", title: "Low Tide at Dusk", medium: "painting",
        dimensions: "24 x 36 in", price_cents: 68_000,
        description: "A wide horizon in muted blue and rust orange as the tide pulls back over wet sand. " \
                      "Palette-knife strokes build texture into the foreground rocks. Painted en plein air " \
                      "over three sessions on the Oregon coast." },
      { seller_email: "noah@example.com", title: "Kitchen Table, Late Morning", medium: "painting",
        dimensions: "18 x 24 in", price_cents: 42_000, quantity: 2,
        description: "Light crosses a cluttered kitchen table, catching the rim of a coffee cup and a " \
                      "half-folded newspaper. Loose brushwork keeps the scene from feeling staged. Part of " \
                      "an ongoing series on domestic quiet." },
      { seller_email: "priya@example.com", title: "Field Study No. 12", medium: "painting",
        dimensions: "30 x 40 in", price_cents: 95_000,
        description: "Rows of lavender recede toward a treeline under a bruised summer sky. Thin glazes sit " \
                      "over a toned ground, so the underpainting shows through the purple. Twelfth canvas in " \
                      "a series painted across one growing season." },
      { seller_email: "leo@example.com", title: "Harbor Fog, Early Shift", medium: "painting",
        dimensions: "20 x 30 in", price_cents: 76_000,
        description: "Trawlers sit at anchor behind a scrim of morning fog, hulls barely distinct from the " \
                      "water. A single sodium lamp on the dock anchors the composition. Reference photos came " \
                      "from a week spent on a working harbor." },

      { seller_email: "maya@example.com", title: "Nine Herons", medium: "print",
        dimensions: "16 x 20 in", price_cents: 12_000,
        description: "Nine herons in profile, carved in a single block and printed in three passes of grey " \
                      "ink. Each bird holds a different angle of the neck, drawn from a winter spent at a " \
                      "tidal marsh. Edition of thirty, hand-numbered." },
      { seller_email: "noah@example.com", title: "Terminal, Platform 4", medium: "print",
        dimensions: "18 x 24 in", price_cents: 15_000,
        description: "A commuter platform rendered in four flat colors, the crowd reduced to silhouettes and " \
                      "one lit sign. Screenprinted by hand in small batches. Part of a set of transit prints " \
                      "made from station sketches." },
      { seller_email: "priya@example.com", title: "Marigold Study", medium: "print",
        dimensions: "11 x 14 in", price_cents: 6_000,
        description: "A single marigold stem printed in two risograph passes, orange over a warm grey. The " \
                      "registration sits slightly loose on purpose, so the layers separate at the edges. Riso " \
                      "printing keeps each run different from the last." },
      { seller_email: "leo@example.com", title: "Night Freight", medium: "print",
        dimensions: "14 x 18 in", price_cents: 22_000,
        description: "A freight train crosses a trestle bridge at night, the headlamp the only bright point " \
                      "on the plate. Deep bitten lines carry the dark, aquatint fills the sky. Printed on a " \
                      "hand press in an edition of twelve." },

      { seller_email: "maya@example.com", title: "Ash-Glazed Tea Bowl", medium: "ceramic",
        dimensions: "4 x 4 x 3 in", price_cents: 8_500, quantity: 2,
        description: "A stoneware tea bowl fired with wood ash landing across the shoulder in a natural " \
                      "drip. The foot is trimmed thin and left unglazed to show the clay body. Fired in a " \
                      "three-day anagama firing." },
      { seller_email: "noah@example.com", title: "Speckled Stoneware Pitcher", medium: "ceramic",
        dimensions: "9 x 6 x 6 in", price_cents: 14_000,
        description: "A pitcher thrown in a speckled stoneware clay, pulled handle attached while the body " \
                      "is still soft. The spout is cut for a clean pour rather than a decorative flare. " \
                      "Glazed in a satin oatmeal that breaks over the throwing rings." },
      { seller_email: "priya@example.com", title: "Woodfired Vase, Tall", medium: "ceramic",
        dimensions: "14 x 6 x 6 in", price_cents: 32_000,
        description: "A tall thrown vase, fired unglazed in a wood kiln so ash and flame draw a map of " \
                      "color across the surface. No two sides read the same. Fourteen inches gives it enough " \
                      "height for a single branch or a full arrangement." },
      { seller_email: "leo@example.com", title: "Salt-Glazed Serving Bowl", medium: "ceramic",
        dimensions: "12 x 12 x 4 in", price_cents: 19_500,
        description: "A wide serving bowl salt-glazed to an orange-peel texture, the rim left slightly " \
                      "irregular from the wheel. Food-safe and built for daily use rather than display. " \
                      "Fires to a warm amber wherever the flame reaches it directly." },

      { seller_email: "maya@example.com", title: "Indigo Shibori Wall Hanging", medium: "textile",
        dimensions: "36 x 48 in", price_cents: 24_000,
        description: "A cotton panel bound and dyed in indigo using a folded arashi technique, the pattern " \
                      "reading as a field of diagonal rain. Dyed in four successive baths to build depth. " \
                      "Hung from a raw dowel with visible stitching." },
      { seller_email: "noah@example.com", title: "Handwoven Mohair Throw", medium: "textile",
        dimensions: "50 x 70 in", price_cents: 32_000,
        description: "A plain-weave throw in undyed mohair and a fine wool warp, woven on a floor loom over " \
                      "two weeks. The natural fiber colors run cream through charcoal without any dye. " \
                      "Fringe is hand-twisted at both ends." },
      { seller_email: "priya@example.com", title: "Rag-Rug Runner, Ochre", medium: "textile",
        dimensions: "24 x 72 in", price_cents: 18_000,
        description: "A rag rug woven from strips of reclaimed cotton fabric in a range of ochre and rust. " \
                      "Each strip carries a trace of its previous life as clothing or bedding. Reversible, " \
                      "with a matching pattern on both sides." },
      { seller_email: "leo@example.com", title: "Naturally Dyed Silk Scarf", medium: "textile",
        dimensions: "18 x 72 in", price_cents: 9_500,
        description: "A silk habotai scarf dyed with onion skin and marigold, giving a gradient from pale " \
                      "gold to deep amber. Hand-hemmed along all four edges. Each dye lot varies with the " \
                      "season's plant material." },

      { seller_email: "maya@example.com", title: "Standing Figure in Reclaimed Oak", medium: "sculpture",
        dimensions: "22 x 8 x 8 in", price_cents: 185_000, quantity: 2,
        description: "A standing figure carved from a single piece of reclaimed oak beam, the surface left " \
                      "with visible chisel marks. The grain of the old beam runs through the torso like a " \
                      "seam. Finished with hand-rubbed oil rather than a film coating." },
      { seller_email: "noah@example.com", title: "Welded Steel Corvid", medium: "sculpture",
        dimensions: "16 x 10 x 20 in", price_cents: 96_000,
        description: "A crow built from welded steel plate and rod, the feathers suggested with cut sheet " \
                      "rather than modeled in detail. The finish is a raw steel patina, left to develop rust " \
                      "over time. Stands free on a flat steel base." },
      { seller_email: "priya@example.com", title: "Cast Bronze Seed Pod", medium: "sculpture",
        dimensions: "10 x 6 x 6 in", price_cents: 145_000,
        description: "A seed pod form cast in bronze from a wax original, patinated to a deep green over " \
                      "brown. The surface holds the fine texture of the original carving. Cast in a lost-wax " \
                      "edition of eight." },
      { seller_email: "leo@example.com", title: "Balanced Stone Cairn", medium: "sculpture",
        dimensions: "30 x 12 x 12 in", price_cents: 68_000,
        description: "Four fieldstones stacked and pinned along a hidden steel rod, the balance point of " \
                      "each stone left visible. Stone comes from a single riverbed, chosen for color and " \
                      "grain across the set. Built for an outdoor garden setting." },

      { seller_email: "maya@example.com", title: "Quarry at First Light", medium: "photography",
        dimensions: "24 x 36 in", price_cents: 45_000,
        description: "An abandoned quarry photographed at first light, mist still sitting in the lowest " \
                      "cut. Printed as an archival pigment print on cotton rag paper. Shot on medium-format " \
                      "film and scanned at high resolution." },
      { seller_email: "noah@example.com", title: "Neon After Rain", medium: "photography",
        dimensions: "20 x 30 in", price_cents: 38_000,
        description: "A city street after rain, neon signs doubled in the wet pavement. A long exposure " \
                      "holds the blur of a single passing car. Printed in a limited run of fifteen." },
      { seller_email: "priya@example.com", title: "Salt Flats, Noon", medium: "photography",
        dimensions: "30 x 40 in", price_cents: 52_000,
        description: "A salt flat under a noon sun, the horizon line barely visible between white ground and " \
                      "white sky. A lone figure stands near the frame's edge for scale. Printed large to hold " \
                      "the flatness of the light." },
      { seller_email: "leo@example.com", title: "Portrait of a Welder", medium: "photography",
        dimensions: "16 x 20 in", price_cents: 29_500,
        description: "A welder mid-task, arc light catching the edge of the mask and glove. Shot on " \
                      "black-and-white film and printed in a wet darkroom. Part of a portrait series on " \
                      "trade work." },

      { seller_email: "noah@example.com", title: "Untitled Charcoal Study", medium: "painting",
        dimensions: "18 x 24 in", price_cents: 15_000, status: DRAFT,
        description: "A charcoal figure study from a single studio session, kept loose and unfinished. " \
                      "Working drawing for a larger painting still in progress." },
      { seller_email: "priya@example.com", title: "Waxed Linen Sampler", medium: "textile",
        dimensions: "20 x 20 in", price_cents: 12_000, status: DRAFT,
        description: "A test panel of waxed linen dyed in three tannin baths, made to check color before a " \
                      "full-size piece. Not yet mounted or finished at the edges." },
      { seller_email: "leo@example.com", title: "Kiln Test Tiles, Series 3", medium: "ceramic",
        dimensions: "6 x 6 in each", price_cents: 4_000, status: DRAFT,
        description: "A set of glaze test tiles from the third round of a new ash glaze recipe. Kept as a " \
                      "reference rather than sold, listed here as a draft." },

      { seller_email: "maya@example.com", title: "Copper Patina Bowl", medium: "ceramic",
        dimensions: "10 x 10 x 4 in", price_cents: 22_000, status: SOLD, quantity: 0,
        description: "A thrown bowl finished with a copper-oxide wash that fires to a mottled green and " \
                      "black. The last piece from a small batch fired in the spring." },
      { seller_email: "leo@example.com", title: "Wet Plate Collodion Portrait", medium: "photography",
        dimensions: "8 x 10 in", price_cents: 62_000, status: SOLD, quantity: 0,
        description: "A tintype portrait made with the wet plate collodion process, each plate unique and " \
                      "unrepeatable. A one-of-a-kind piece, now sold." }
    ].freeze

    def create_all
      seller_ids = Seller.pluck(:email, :id).to_h

      RECORDS.each do |record|
        Listing.create!(
          seller_id: seller_ids.fetch(record.fetch(:seller_email)),
          title: record.fetch(:title),
          slug: record.fetch(:title).parameterize,
          description: record.fetch(:description),
          price_cents: record.fetch(:price_cents),
          quantity: record.fetch(:quantity, 1),
          status: record.fetch(:status, FOR_SALE),
          medium: record.fetch(:medium),
          dimensions: record.fetch(:dimensions)
        )
      end
    end
  end
end

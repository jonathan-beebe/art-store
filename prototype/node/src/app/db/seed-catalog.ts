import type { ActionContext } from '../actions/action-context.ts'
import { changeListingStatus } from '../actions/listings/change-listing-status.ts'
import { createListing } from '../actions/listings/create-listing.ts'
import { removeListing } from '../actions/moderation/remove-listing.ts'
import { fixedClock } from '../clock.ts'
import { cents } from '../core/money.ts'
import type { AppDatabase } from './database.ts'

const CREATED_AT = new Date('2026-06-05T00:00:00.000Z')
const REMOVED_AT = new Date('2026-07-02T09:00:00.000Z')

type CatalogStatus = 'for_sale' | 'draft' | 'sold'

type CatalogRecord = {
  sellerEmail: string
  title: string
  medium: string
  dimensions: string
  priceCents: number
  description: string
  quantity?: number
  status?: CatalogStatus
}

/** Ported from `prototype/rails/src/db/seeds/listings.rb`: 24 for_sale across six
 * media, three drafts, two sold out. Three of the for_sale listings start at
 * quantity 2 so the order history can sell one unit and leave them listed. */
export const CATALOG: readonly CatalogRecord[] = [
  {
    sellerEmail: 'maya@example.com',
    title: 'Low Tide at Dusk',
    medium: 'painting',
    dimensions: '24 x 36 in',
    priceCents: 68_000,
    description:
      'A wide horizon in muted blue and rust orange as the tide pulls back over wet sand. Palette-knife strokes build texture into the foreground rocks. Painted en plein air over three sessions on the Oregon coast.',
  },
  {
    sellerEmail: 'noah@example.com',
    title: 'Kitchen Table, Late Morning',
    medium: 'painting',
    dimensions: '18 x 24 in',
    priceCents: 42_000,
    quantity: 2,
    description:
      'Light crosses a cluttered kitchen table, catching the rim of a coffee cup and a half-folded newspaper. Loose brushwork keeps the scene from feeling staged. Part of an ongoing series on domestic quiet.',
  },
  {
    sellerEmail: 'priya@example.com',
    title: 'Field Study No. 12',
    medium: 'painting',
    dimensions: '30 x 40 in',
    priceCents: 95_000,
    description:
      'Rows of lavender recede toward a treeline under a bruised summer sky. Thin glazes sit over a toned ground, so the underpainting shows through the purple. Twelfth canvas in a series painted across one growing season.',
  },
  {
    sellerEmail: 'leo@example.com',
    title: 'Harbor Fog, Early Shift',
    medium: 'painting',
    dimensions: '20 x 30 in',
    priceCents: 76_000,
    description:
      'Trawlers sit at anchor behind a scrim of morning fog, hulls barely distinct from the water. A single sodium lamp on the dock anchors the composition. Reference photos came from a week spent on a working harbor.',
  },
  {
    sellerEmail: 'maya@example.com',
    title: 'Nine Herons',
    medium: 'print',
    dimensions: '16 x 20 in',
    priceCents: 12_000,
    description:
      'Nine herons in profile, carved in a single block and printed in three passes of grey ink. Each bird holds a different angle of the neck, drawn from a winter spent at a tidal marsh. Edition of thirty, hand-numbered.',
  },
  {
    sellerEmail: 'noah@example.com',
    title: 'Terminal, Platform 4',
    medium: 'print',
    dimensions: '18 x 24 in',
    priceCents: 15_000,
    description:
      'A commuter platform rendered in four flat colors, the crowd reduced to silhouettes and one lit sign. Screenprinted by hand in small batches. Part of a set of transit prints made from station sketches.',
  },
  {
    sellerEmail: 'priya@example.com',
    title: 'Marigold Study',
    medium: 'print',
    dimensions: '11 x 14 in',
    priceCents: 6_000,
    description:
      'A single marigold stem printed in two risograph passes, orange over a warm grey. The registration sits slightly loose on purpose, so the layers separate at the edges. Riso printing keeps each run different from the last.',
  },
  {
    sellerEmail: 'leo@example.com',
    title: 'Night Freight',
    medium: 'print',
    dimensions: '14 x 18 in',
    priceCents: 22_000,
    description:
      'A freight train crosses a trestle bridge at night, the headlamp the only bright point on the plate. Deep bitten lines carry the dark, aquatint fills the sky. Printed on a hand press in an edition of twelve.',
  },
  {
    sellerEmail: 'maya@example.com',
    title: 'Ash-Glazed Tea Bowl',
    medium: 'ceramic',
    dimensions: '4 x 4 x 3 in',
    priceCents: 8_500,
    quantity: 2,
    description:
      'A stoneware tea bowl fired with wood ash landing across the shoulder in a natural drip. The foot is trimmed thin and left unglazed to show the clay body. Fired in a three-day anagama firing.',
  },
  {
    sellerEmail: 'noah@example.com',
    title: 'Speckled Stoneware Pitcher',
    medium: 'ceramic',
    dimensions: '9 x 6 x 6 in',
    priceCents: 14_000,
    description:
      'A pitcher thrown in a speckled stoneware clay, pulled handle attached while the body is still soft. The spout is cut for a clean pour rather than a decorative flare. Glazed in a satin oatmeal that breaks over the throwing rings.',
  },
  {
    sellerEmail: 'priya@example.com',
    title: 'Woodfired Vase, Tall',
    medium: 'ceramic',
    dimensions: '14 x 6 x 6 in',
    priceCents: 32_000,
    description:
      'A tall thrown vase, fired unglazed in a wood kiln so ash and flame draw a map of color across the surface. No two sides read the same. Fourteen inches gives it enough height for a single branch or a full arrangement.',
  },
  {
    sellerEmail: 'leo@example.com',
    title: 'Salt-Glazed Serving Bowl',
    medium: 'ceramic',
    dimensions: '12 x 12 x 4 in',
    priceCents: 19_500,
    description:
      'A wide serving bowl salt-glazed to an orange-peel texture, the rim left slightly irregular from the wheel. Food-safe and built for daily use rather than display. Fires to a warm amber wherever the flame reaches it directly.',
  },
  {
    sellerEmail: 'maya@example.com',
    title: 'Indigo Shibori Wall Hanging',
    medium: 'textile',
    dimensions: '36 x 48 in',
    priceCents: 24_000,
    description:
      'A cotton panel bound and dyed in indigo using a folded arashi technique, the pattern reading as a field of diagonal rain. Dyed in four successive baths to build depth. Hung from a raw dowel with visible stitching.',
  },
  {
    sellerEmail: 'noah@example.com',
    title: 'Handwoven Mohair Throw',
    medium: 'textile',
    dimensions: '50 x 70 in',
    priceCents: 32_000,
    description:
      'A plain-weave throw in undyed mohair and a fine wool warp, woven on a floor loom over two weeks. The natural fiber colors run cream through charcoal without any dye. Fringe is hand-twisted at both ends.',
  },
  {
    sellerEmail: 'priya@example.com',
    title: 'Rag-Rug Runner, Ochre',
    medium: 'textile',
    dimensions: '24 x 72 in',
    priceCents: 18_000,
    description:
      'A rag rug woven from strips of reclaimed cotton fabric in a range of ochre and rust. Each strip carries a trace of its previous life as clothing or bedding. Reversible, with a matching pattern on both sides.',
  },
  {
    sellerEmail: 'leo@example.com',
    title: 'Naturally Dyed Silk Scarf',
    medium: 'textile',
    dimensions: '18 x 72 in',
    priceCents: 9_500,
    description:
      "A silk habotai scarf dyed with onion skin and marigold, giving a gradient from pale gold to deep amber. Hand-hemmed along all four edges. Each dye lot varies with the season's plant material.",
  },
  {
    sellerEmail: 'maya@example.com',
    title: 'Standing Figure in Reclaimed Oak',
    medium: 'sculpture',
    dimensions: '22 x 8 x 8 in',
    priceCents: 185_000,
    quantity: 2,
    description:
      'A standing figure carved from a single piece of reclaimed oak beam, the surface left with visible chisel marks. The grain of the old beam runs through the torso like a seam. Finished with hand-rubbed oil rather than a film coating.',
  },
  {
    sellerEmail: 'noah@example.com',
    title: 'Welded Steel Corvid',
    medium: 'sculpture',
    dimensions: '16 x 10 x 20 in',
    priceCents: 96_000,
    description:
      'A crow built from welded steel plate and rod, the feathers suggested with cut sheet rather than modeled in detail. The finish is a raw steel patina, left to develop rust over time. Stands free on a flat steel base.',
  },
  {
    sellerEmail: 'priya@example.com',
    title: 'Cast Bronze Seed Pod',
    medium: 'sculpture',
    dimensions: '10 x 6 x 6 in',
    priceCents: 145_000,
    description:
      'A seed pod form cast in bronze from a wax original, patinated to a deep green over brown. The surface holds the fine texture of the original carving. Cast in a lost-wax edition of eight.',
  },
  {
    sellerEmail: 'leo@example.com',
    title: 'Balanced Stone Cairn',
    medium: 'sculpture',
    dimensions: '30 x 12 x 12 in',
    priceCents: 68_000,
    description:
      'Four fieldstones stacked and pinned along a hidden steel rod, the balance point of each stone left visible. Stone comes from a single riverbed, chosen for color and grain across the set. Built for an outdoor garden setting.',
  },
  {
    sellerEmail: 'maya@example.com',
    title: 'Quarry at First Light',
    medium: 'photography',
    dimensions: '24 x 36 in',
    priceCents: 45_000,
    description:
      'An abandoned quarry photographed at first light, mist still sitting in the lowest cut. Printed as an archival pigment print on cotton rag paper. Shot on medium-format film and scanned at high resolution.',
  },
  {
    sellerEmail: 'noah@example.com',
    title: 'Neon After Rain',
    medium: 'photography',
    dimensions: '20 x 30 in',
    priceCents: 38_000,
    description:
      'A city street after rain, neon signs doubled in the wet pavement. A long exposure holds the blur of a single passing car. Printed in a limited run of fifteen.',
  },
  {
    sellerEmail: 'priya@example.com',
    title: 'Salt Flats, Noon',
    medium: 'photography',
    dimensions: '30 x 40 in',
    priceCents: 52_000,
    description:
      "A salt flat under a noon sun, the horizon line barely visible between white ground and white sky. A lone figure stands near the frame's edge for scale. Printed large to hold the flatness of the light.",
  },
  {
    sellerEmail: 'leo@example.com',
    title: 'Portrait of a Welder',
    medium: 'photography',
    dimensions: '16 x 20 in',
    priceCents: 29_500,
    description:
      'A welder mid-task, arc light catching the edge of the mask and glove. Shot on black-and-white film and printed in a wet darkroom. Part of a portrait series on trade work.',
  },
  {
    sellerEmail: 'noah@example.com',
    title: 'Untitled Charcoal Study',
    medium: 'painting',
    dimensions: '18 x 24 in',
    priceCents: 15_000,
    status: 'draft',
    description:
      'A charcoal figure study from a single studio session, kept loose and unfinished. Working drawing for a larger painting still in progress.',
  },
  {
    sellerEmail: 'priya@example.com',
    title: 'Waxed Linen Sampler',
    medium: 'textile',
    dimensions: '20 x 20 in',
    priceCents: 12_000,
    status: 'draft',
    description:
      'A test panel of waxed linen dyed in three tannin baths, made to check color before a full-size piece. Not yet mounted or finished at the edges.',
  },
  {
    sellerEmail: 'leo@example.com',
    title: 'Kiln Test Tiles, Series 3',
    medium: 'ceramic',
    dimensions: '6 x 6 in each',
    priceCents: 4_000,
    status: 'draft',
    description:
      'A set of glaze test tiles from the third round of a new ash glaze recipe. Kept as a reference rather than sold, listed here as a draft.',
  },
  {
    sellerEmail: 'maya@example.com',
    title: 'Copper Patina Bowl',
    medium: 'ceramic',
    dimensions: '10 x 10 x 4 in',
    priceCents: 22_000,
    status: 'sold',
    quantity: 0,
    description:
      'A thrown bowl finished with a copper-oxide wash that fires to a mottled green and black. The last piece from a small batch fired in the spring.',
  },
  {
    sellerEmail: 'leo@example.com',
    title: 'Wet Plate Collodion Portrait',
    medium: 'photography',
    dimensions: '8 x 10 in',
    priceCents: 62_000,
    status: 'sold',
    quantity: 0,
    description:
      'A tintype portrait made with the wet plate collodion process, each plate unique and unrepeatable. A one-of-a-kind piece, now sold.',
  },
]

/** The for_sale listing an admin takes down temporarily, still counted among the 24. */
export const REMOVED_LISTING_TITLE = 'Night Freight'
const REMOVAL_REASON = 'Under review: a buyer reported the print does not match the listed edition size.'

export type SeededCatalog = {
  listingIdsByTitle: Record<string, number>
}

/** Creates every catalog listing through the seller portal's own actions and
 * takes one for_sale listing down with a temporary removal. */
export async function seedCatalog(
  db: AppDatabase,
  sellerIdsByEmail: Record<string, number>,
  adminId: number,
): Promise<SeededCatalog> {
  const context: ActionContext = { db, clock: fixedClock(CREATED_AT) }
  const listingIdsByTitle: Record<string, number> = {}

  for (const record of CATALOG) {
    const sellerId = requireSellerId(sellerIdsByEmail, record.sellerEmail)
    const listing = await createListing(context, {
      sellerId,
      draft: {
        title: record.title,
        description: record.description,
        medium: record.medium,
        dimensions: record.dimensions,
        priceCents: cents(record.priceCents),
        quantity: record.quantity ?? 1,
      },
    })

    await advanceToStatus(context, listing.id, record.status ?? 'for_sale')
    listingIdsByTitle[record.title] = listing.id
  }

  await removeListing(
    { db, clock: fixedClock(REMOVED_AT) },
    {
      listingId: requireListingId(listingIdsByTitle, REMOVED_LISTING_TITLE),
      adminId,
      kind: 'temporary',
      reason: REMOVAL_REASON,
    },
  )

  return { listingIdsByTitle }
}

/** A listing is born a draft, so reaching `for_sale` or `sold` replays the
 * single-hop transitions the status table allows. */
async function advanceToStatus(
  context: ActionContext,
  listingId: number,
  target: CatalogStatus,
): Promise<void> {
  if (target === 'draft') return

  await changeListingStatus(context, { listingId, status: 'for_sale' })
  if (target === 'sold') {
    await changeListingStatus(context, { listingId, status: 'sold' })
  }
}

function requireSellerId(sellerIdsByEmail: Record<string, number>, email: string): number {
  const sellerId = sellerIdsByEmail[email]
  if (sellerId === undefined) {
    throw new Error(`seedCatalog: no seeded seller for ${email}`)
  }
  return sellerId
}

function requireListingId(listingIdsByTitle: Record<string, number>, title: string): number {
  const listingId = listingIdsByTitle[title]
  if (listingId === undefined) {
    throw new Error(`seedCatalog: no seeded listing titled ${title}`)
  }
  return listingId
}

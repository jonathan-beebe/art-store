import type { ActionContext } from '../actions/action-context.ts'
import { changeListingStatus, changedListing } from '../actions/listings/change-listing-status.ts'
import { createListing } from '../actions/listings/create-listing.ts'
import { removeListing } from '../actions/moderation/remove-listing.ts'
import { fixedClock } from '../clock.ts'
import type {
  AdminId,
  ListingId,
  SellerId,
} from '../core/ids/entity-ids.ts'
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

/** Same shape the Rails seed carries: 24 for_sale across six media, three
 * drafts, two sold out. Three of the for_sale listings start at quantity 2 so
 * the order history can sell one unit and leave them listed. */
export const CATALOG: readonly CatalogRecord[] = [
  {
    sellerEmail: 'molly@example.com',
    title: 'The Burrow at Dusk',
    medium: 'painting',
    dimensions: '24 x 36 in',
    priceCents: 68_000,
    description:
      'The crooked silhouette of the Burrow leans into a violet evening sky, one window lit in the kitchen. Palette-knife strokes carry the improbable stack of upper storeys. Painted from the orchard gate over three summer evenings.',
  },
  {
    sellerEmail: 'dean@example.com',
    title: 'Gryffindor Common Room, Late Morning',
    medium: 'painting',
    dimensions: '18 x 24 in',
    priceCents: 42_000,
    quantity: 2,
    description:
      'Sun crosses a worn armchair by the fire, catching an abandoned chess set and a half-rolled essay. Loose brushwork keeps the scene from feeling staged. Part of an ongoing series on quiet corners of the castle.',
  },
  {
    sellerEmail: 'sybill@example.com',
    title: 'Lavender Fields from the North Tower',
    medium: 'painting',
    dimensions: '30 x 40 in',
    priceCents: 95_000,
    description:
      'Rows of lavender recede toward the Forbidden Forest under a bruised summer sky, seen from the tower window. Thin glazes sit over a toned ground, so the underpainting shows through the purple. The composition arrived in a vision; the painting took a season.',
  },
  {
    sellerEmail: 'colin@example.com',
    title: 'Hogsmeade Fog, Early Shift',
    medium: 'painting',
    dimensions: '20 x 30 in',
    priceCents: 76_000,
    description:
      'The high street sits behind a scrim of morning fog, shopfronts barely distinct from the snow. A single lamp outside the Three Broomsticks anchors the composition. Reference photographs came from a week of dawn walks before the shops opened.',
  },
  {
    sellerEmail: 'molly@example.com',
    title: 'Nine Owls',
    medium: 'print',
    dimensions: '16 x 20 in',
    priceCents: 12_000,
    description:
      'Nine owls in profile, carved in a single block and printed in three passes of grey ink. Each bird holds a different tilt of the head, drawn from a winter of post arriving at the kitchen window. Edition of thirty, hand-numbered.',
  },
  {
    sellerEmail: 'dean@example.com',
    title: 'Platform Nine and Three-Quarters',
    medium: 'print',
    dimensions: '18 x 24 in',
    priceCents: 15_000,
    description:
      'The platform rendered in four flat colors, the crowd reduced to silhouettes, trolleys, and one scarlet engine. Screenprinted by hand in small batches. Part of a set of journey prints made from first-of-September sketches.',
  },
  {
    sellerEmail: 'sybill@example.com',
    title: 'Tea Leaf Study',
    medium: 'print',
    dimensions: '11 x 14 in',
    priceCents: 6_000,
    description:
      'The bottom of a teacup printed in two risograph passes, sepia over a warm grey. The registration sits slightly loose on purpose, so the leaves refuse to settle into one reading. Whether you see the Grim is entirely your own affair.',
  },
  {
    sellerEmail: 'colin@example.com',
    title: 'Hogwarts Express, Night Crossing',
    medium: 'print',
    dimensions: '14 x 18 in',
    priceCents: 22_000,
    description:
      'The Express crosses the viaduct at night, the headlamp the only bright point on the plate. Deep bitten lines carry the dark, aquatint fills the sky. Printed on a hand press in an edition of twelve.',
  },
  {
    sellerEmail: 'molly@example.com',
    title: 'Burrow Kitchen Tea Bowl',
    medium: 'ceramic',
    dimensions: '4 x 4 x 3 in',
    priceCents: 8_500,
    quantity: 2,
    description:
      'A stoneware tea bowl fired with orchard-wood ash landing across the shoulder in a natural drip. The foot is trimmed thin and left unglazed to show the clay body. Thrown between batches of bread on a quiet Burrow morning.',
  },
  {
    sellerEmail: 'dean@example.com',
    title: 'Butterbeer Pitcher, Speckled Stoneware',
    medium: 'ceramic',
    dimensions: '9 x 6 x 6 in',
    priceCents: 14_000,
    description:
      'A pitcher thrown in a speckled stoneware clay, pulled handle attached while the body is still soft. The spout is cut for a clean pour with a proper head of foam. Glazed in a satin butterscotch that breaks over the throwing rings.',
  },
  {
    sellerEmail: 'sybill@example.com',
    title: 'Divination Tower Vase, Tall',
    medium: 'ceramic',
    dimensions: '14 x 6 x 6 in',
    priceCents: 32_000,
    description:
      'A tall thrown vase, fired unglazed in a wood kiln so ash and flame draw a map of portents across the surface. No two sides read the same, which is rather the point. Fourteen inches gives it enough height for a single branch or a full arrangement.',
  },
  {
    sellerEmail: 'colin@example.com',
    title: 'Great Hall Serving Bowl',
    medium: 'ceramic',
    dimensions: '12 x 12 x 4 in',
    priceCents: 19_500,
    description:
      'A wide serving bowl salt-glazed to an orange-peel texture, the rim left slightly irregular from the wheel. Food-safe and built for a crowded table rather than display. Fires to a warm amber wherever the flame reaches it directly.',
  },
  {
    sellerEmail: 'molly@example.com',
    title: 'Knitted Letter Jumper, Wall Piece',
    medium: 'textile',
    dimensions: '36 x 48 in',
    priceCents: 24_000,
    description:
      'A hand-knitted jumper in deep maroon with a large gold initial, mounted flat as a wall piece. The letter is worked in intarsia, not stitched on after. Commissions take a month; December orders should allow for the Christmas rush.',
  },
  {
    sellerEmail: 'dean@example.com',
    title: 'House Scarf Throw, Scarlet and Gold',
    medium: 'textile',
    dimensions: '50 x 70 in',
    priceCents: 32_000,
    description:
      'A plain-weave throw in scarlet wool and a fine gold warp, woven on a floor loom over two weeks. Wide bands keep it a blanket rather than a costume piece. Fringe is hand-twisted at both ends.',
  },
  {
    sellerEmail: 'sybill@example.com',
    title: 'Patchwork Shawl Runner, Ochre',
    medium: 'textile',
    dimensions: '24 x 72 in',
    priceCents: 18_000,
    description:
      'A runner woven from strips of retired shawls in a range of ochre and rust, each with a history of draughty tower evenings. Every strip carries a trace of its previous life. Reversible, with a matching pattern on both sides.',
  },
  {
    sellerEmail: 'colin@example.com',
    title: 'Naturally Dyed Silk Scarf',
    medium: 'textile',
    dimensions: '18 x 72 in',
    priceCents: 9_500,
    description:
      "A silk habotai scarf dyed with onion skin and marigold from the greenhouse compost heap, giving a gradient from pale gold to deep amber. Hand-hemmed along all four edges. Each dye lot varies with the season's plant material.",
  },
  {
    sellerEmail: 'molly@example.com',
    title: 'Garden Gnome in Reclaimed Oak',
    medium: 'sculpture',
    dimensions: '22 x 8 x 8 in',
    priceCents: 185_000,
    quantity: 2,
    description:
      'A garden gnome carved from a single piece of reclaimed oak beam, caught mid-scowl the moment before it bolts. The grain of the old beam runs through the torso like a seam. Finished with hand-rubbed oil; guaranteed not to bite.',
  },
  {
    sellerEmail: 'dean@example.com',
    title: 'Welded Steel Hippogriff',
    medium: 'sculpture',
    dimensions: '16 x 10 x 20 in',
    priceCents: 96_000,
    description:
      'A hippogriff built from welded steel plate and rod, the feathers suggested with cut sheet rather than modeled in detail. The finish is a raw steel patina, left to develop rust over time. Approach the sculpture however you like; it has never once demanded a bow.',
  },
  {
    sellerEmail: 'sybill@example.com',
    title: 'Cast Bronze Seeing Orb',
    medium: 'sculpture',
    dimensions: '10 x 6 x 6 in',
    priceCents: 145_000,
    description:
      'An orb and stand cast in bronze from a wax original, patinated to a deep green over brown. The surface holds the fine texture of the original carving, clouded the way a proper glass should be. Cast in a lost-wax edition of eight.',
  },
  {
    sellerEmail: 'colin@example.com',
    title: 'Standing Stones, Black Lake',
    medium: 'sculpture',
    dimensions: '30 x 12 x 12 in',
    priceCents: 68_000,
    description:
      'Four lakeshore stones stacked and pinned along a hidden steel rod, the balance point of each stone left visible. Stone comes from a single stretch of the Black Lake shore, chosen for color and grain across the set. Built for an outdoor garden setting.',
  },
  {
    sellerEmail: 'molly@example.com',
    title: 'The Orchard at First Light',
    medium: 'photography',
    dimensions: '24 x 36 in',
    priceCents: 45_000,
    description:
      'The Burrow orchard photographed at first light, mist still sitting between the apple rows where the children practice Quidditch. Printed as an archival pigment print on cotton rag paper. Shot on medium-format film and scanned at high resolution.',
  },
  {
    sellerEmail: 'dean@example.com',
    title: 'Diagon Alley After Rain',
    medium: 'photography',
    dimensions: '20 x 30 in',
    priceCents: 38_000,
    description:
      'The alley after rain, shop signs doubled in the wet cobbles. A long exposure holds the blur of a single hurrying cloak. Printed in a limited run of fifteen.',
  },
  {
    sellerEmail: 'sybill@example.com',
    title: 'The Great Lake, Noon',
    medium: 'photography',
    dimensions: '30 x 40 in',
    priceCents: 52_000,
    description:
      "The lake under a noon sun, the horizon line barely visible between white water and white sky. A lone figure stands near the frame's edge for scale; the tentacle was not planned. Printed large to hold the flatness of the light.",
  },
  {
    sellerEmail: 'colin@example.com',
    title: 'Portrait of a Gamekeeper',
    medium: 'photography',
    dimensions: '16 x 20 in',
    priceCents: 29_500,
    description:
      'The gamekeeper mid-task at the pumpkin patch, forge light from the hut catching the edge of a moleskin coat. Shot on black-and-white film and printed in a wet darkroom. Part of a portrait series on the castle grounds staff.',
  },
  {
    sellerEmail: 'dean@example.com',
    title: 'Quidditch Keeper, Charcoal Study',
    medium: 'painting',
    dimensions: '18 x 24 in',
    priceCents: 15_000,
    status: 'draft',
    description:
      'A charcoal study of a keeper hanging off the left-most hoop, kept loose and unfinished. Working drawing for a larger match painting still in progress.',
  },
  {
    sellerEmail: 'sybill@example.com',
    title: 'Tasseled Shawl Sampler',
    medium: 'textile',
    dimensions: '20 x 20 in',
    priceCents: 12_000,
    status: 'draft',
    description:
      'A test panel of waxed linen dyed in three tannin baths, made to check color before a full-size shawl. Not yet mounted or finished at the edges.',
  },
  {
    sellerEmail: 'colin@example.com',
    title: 'Glaze Test Tiles, Series 3',
    medium: 'ceramic',
    dimensions: '6 x 6 in each',
    priceCents: 4_000,
    status: 'draft',
    description:
      'A set of glaze test tiles from the third round of a new ash glaze recipe. Kept as a reference rather than sold, listed here as a draft.',
  },
  {
    sellerEmail: 'molly@example.com',
    title: 'Copper Cauldron Bowl',
    medium: 'ceramic',
    dimensions: '10 x 10 x 4 in',
    priceCents: 22_000,
    status: 'sold',
    quantity: 0,
    description:
      'A thrown bowl finished with a copper-oxide wash that fires to a mottled green and black, the shape borrowed from a favorite old cauldron. The last piece from a small batch fired in the spring.',
  },
  {
    sellerEmail: 'colin@example.com',
    title: 'Wet Plate Portrait, Nearly Headless Gentleman',
    medium: 'photography',
    dimensions: '8 x 10 in',
    priceCents: 62_000,
    status: 'sold',
    quantity: 0,
    description:
      'A tintype portrait made with the wet plate collodion process, each plate unique and unrepeatable. The sitter held admirably still apart from the obvious. A one-of-a-kind piece, now sold.',
  },
]

/** The for_sale listing an admin takes down temporarily, still counted among the 24. */
export const REMOVED_LISTING_TITLE = 'Hogwarts Express, Night Crossing'
const REMOVAL_REASON = 'Under review: a buyer reported the print does not match the listed edition size.'

export type SeededCatalog = {
  listingIdsByTitle: Record<string, ListingId>
}

/** Creates every catalog listing through the seller portal's own actions and
 * takes one for_sale listing down with a temporary removal. */
export async function seedCatalog(
  db: AppDatabase,
  sellerIdsByEmail: Record<string, SellerId>,
  adminId: AdminId,
): Promise<SeededCatalog> {
  const context: ActionContext = { db, clock: fixedClock(CREATED_AT) }
  const listingIdsByTitle: Record<string, ListingId> = {}

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
  listingId: ListingId,
  target: CatalogStatus,
): Promise<void> {
  if (target === 'draft') return

  changedListing(await changeListingStatus(context, { listingId, status: 'for_sale' }))
  if (target === 'sold') {
    changedListing(await changeListingStatus(context, { listingId, status: 'sold' }))
  }
}

export function requireSellerId(sellerIdsByEmail: Record<string, SellerId>, email: string): SellerId {
  const sellerId = sellerIdsByEmail[email]
  if (sellerId === undefined) {
    throw new Error(`seedCatalog: no seeded seller for ${email}`)
  }
  return sellerId
}

export function requireListingId(listingIdsByTitle: Record<string, ListingId>, title: string): ListingId {
  const listingId = listingIdsByTitle[title]
  if (listingId === undefined) {
    throw new Error(`seedCatalog: no seeded listing titled ${title}`)
  }
  return listingId
}

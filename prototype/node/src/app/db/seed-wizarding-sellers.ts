import { claimSellerIdentity } from '../actions/auth/claim-seller-identity.ts'
import { changeListingStatus, changedListing } from '../actions/listings/change-listing-status.ts'
import { createListing } from '../actions/listings/create-listing.ts'
import type { ActionContext } from '../actions/action-context.ts'
import { fixedClock } from '../clock.ts'
import { cents } from '../core/money.ts'
import type { AppDatabase } from './database.ts'

const VERIFIED_AT = new Date('2026-08-20T00:00:00.000Z')
const CREATED_AT = new Date('2026-08-21T00:00:00.000Z')

type WizardingListing = {
  title: string
  medium: string
  dimensions: string
  priceCents: number
  quantity: number
  description: string
}

type WizardingSeller = {
  email: string
  name: string
  shopName: string
  listings: readonly WizardingListing[]
}

/** Two more verified sellers a reviewer can sign in as, each with a live
 * catalog. Seeded separately from the demo data so they also land on a
 * database the demo seed already refuses to touch. */
export const WIZARDING_SELLERS: readonly WizardingSeller[] = [
  {
    email: 'neville@example.com',
    name: 'Neville Longbottom',
    shopName: 'Longbottom Botanicals',
    listings: [
      {
        title: 'Mimbulus Mimbletonia, Potted',
        medium: 'plant',
        dimensions: '8 x 5 x 5 in',
        priceCents: 9_500,
        quantity: 1,
        description:
          'A rare grey cactus-like specimen, its surface moving gently as it breathes. Raised from a cutting my great uncle Algie brought back from Assyria. Ships in its own terracotta pot with a full care sheet — do not prod the boils.',
      },
      {
        title: 'Flitterbloom Cutting, Rooted',
        medium: 'plant',
        dimensions: '12 in tendrils',
        priceCents: 4_500,
        quantity: 3,
        description:
          'A rooted Flitterbloom cutting with long swaying tendrils, often mistaken for Devil’s Snare but entirely harmless. Thrives in a bright window and asks for little beyond weekly water. Grown in Greenhouse Three from healthy parent stock.',
      },
      {
        title: 'Puffapod Seed Collection',
        medium: 'plant',
        dimensions: 'tin of 20 pods',
        priceCents: 2_500,
        quantity: 6,
        description:
          'Twenty plump pink Puffapod pods in a lidded tin. Drop one anywhere and it bursts into flower on the spot, so sow them where you mean it. Harvested by hand at full ripeness this season.',
      },
      {
        title: 'Bouncing Bulb, Established',
        medium: 'plant',
        dimensions: '10 x 7 x 7 in',
        priceCents: 6_000,
        quantity: 1,
        description:
          'A well-established Bouncing Bulb, repotted twice and calm for its kind. Keeps to modest hops once it settles into a routine. Sturdy gloves recommended at repotting time; it only wriggles when startled.',
      },
    ],
  },
  {
    email: 'luna@example.com',
    name: 'Luna Lovegood',
    shopName: 'Lovegood Curiosities',
    listings: [
      {
        title: 'The Quibbler, Back-Issue Bundle',
        medium: 'publication',
        dimensions: '8.5 x 11 in, set of 5',
        priceCents: 1_200,
        quantity: 12,
        description:
          'Five assorted back issues of The Quibbler, my father’s magazine, including the Crumple-Horned Snorkack expedition special. Some covers print upside down on purpose. Each bundle is different, which is rather the point.',
      },
      {
        title: 'Spectrespecs',
        medium: 'curio',
        dimensions: '6 x 2 x 1 in',
        priceCents: 3_500,
        quantity: 5,
        description:
          'Pink-and-blue paper spectacles that make Wrackspurts visible as they drift out of people’s ears. Very useful for working out why your thinking has gone fuzzy. Free with some issues of The Quibbler, but these are the sturdier keepsake edition.',
      },
      {
        title: 'Butterbeer Cork Necklace',
        medium: 'jewelry',
        dimensions: '18 in cord',
        priceCents: 1_800,
        quantity: 4,
        description:
          'A necklace of butterbeer corks strung on waxed cord, worn to keep the Nargles away. Each cork is collected personally and threaded by hand. The Nargles have never once bothered me while wearing it.',
      },
      {
        title: 'Dirigible Plum Earrings',
        medium: 'jewelry',
        dimensions: '2 in drop',
        priceCents: 2_200,
        quantity: 3,
        description:
          'A pair of bright orange dirigible plum earrings, carved and painted to float just slightly on a breeze. The plums grow beside our front door and enhance the ability to accept the extraordinary. Hooks are plain silver.',
      },
    ],
  },
]

export type SeedWizardingSummary = {
  sellerCount: number
  listingCount: number
}

/**
 * Claims, profiles, and stocks both wizarding sellers, with every listing
 * for sale. Refuses to run twice: a database that already holds the first
 * seller's email is left untouched and answers `null`.
 */
export async function seedWizardingSellers(db: AppDatabase): Promise<SeedWizardingSummary | null> {
  const firstEmail = WIZARDING_SELLERS[0]?.email ?? ''
  const alreadySeeded = await db
    .selectFrom('sellers')
    .select('id')
    .where('email', '=', firstEmail)
    .executeTakeFirst()
  if (alreadySeeded !== undefined) return null

  const context: ActionContext = { db, clock: fixedClock(CREATED_AT) }
  let listingCount = 0

  for (const seller of WIZARDING_SELLERS) {
    const claimed = await claimSellerIdentity({ db, clock: fixedClock(VERIFIED_AT) }, seller.email)

    await db
      .updateTable('sellers')
      .set({ name: seller.name, shopName: seller.shopName })
      .where('id', '=', claimed.id)
      .execute()

    for (const record of seller.listings) {
      const listing = await createListing(context, {
        sellerId: claimed.id,
        draft: {
          title: record.title,
          description: record.description,
          medium: record.medium,
          dimensions: record.dimensions,
          priceCents: cents(record.priceCents),
          quantity: record.quantity,
        },
      })
      changedListing(await changeListingStatus(context, { listingId: listing.id, status: 'for_sale' }))
      listingCount += 1
    }
  }

  return { sellerCount: WIZARDING_SELLERS.length, listingCount }
}

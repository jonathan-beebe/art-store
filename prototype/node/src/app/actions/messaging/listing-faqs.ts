import type { ActionContext } from '../action-context.ts'
import type { ListingFaq } from '../../db/commerce-schema.ts'

/** Every published entry on one listing, oldest first, as both sites show them. */
export async function listingFaqs(
  { db }: Pick<ActionContext, 'db'>,
  listingId: number,
): Promise<readonly ListingFaq[]> {
  return db
    .selectFrom('listingFaqs')
    .selectAll()
    .where('listingId', '=', listingId)
    .orderBy('id')
    .execute()
}

/** One entry, when it belongs to the listing the URL names. */
export async function findListingFaq(
  { db }: Pick<ActionContext, 'db'>,
  { faqId, listingId }: { faqId: number; listingId: number },
): Promise<ListingFaq | null> {
  const faq = await db
    .selectFrom('listingFaqs')
    .selectAll()
    .where('id', '=', faqId)
    .where('listingId', '=', listingId)
    .executeTakeFirst()

  return faq ?? null
}

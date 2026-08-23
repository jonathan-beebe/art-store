import type { ActionContext } from '../action-context.ts'

/**
 * Takes one entry off the listing page. The thread the answer came from is
 * still there, so publishing it again is one click and no draft state is kept.
 */
export async function unpublishListingFaq({ db }: ActionContext, faqId: number): Promise<void> {
  await db.deleteFrom('listingFaqs').where('id', '=', faqId).execute()
}

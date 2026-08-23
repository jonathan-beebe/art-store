import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'

/**
 * Takes one entry off the listing page. The thread the answer came from is
 * still there, so publishing it again is one click and no draft state is kept.
 */
export async function unpublishListingFaq(context: ActionContext, faqId: number): Promise<void> {
  await runInTransaction(context, async ({ db }) => {
    await db.deleteFrom('listingFaqs').where('id', '=', faqId).execute()
  })
}

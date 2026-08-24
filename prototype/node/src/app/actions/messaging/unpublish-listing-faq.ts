import type { ListingFaqId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'

/**
 * Takes one entry off the listing page. The thread the answer came from is
 * still there, so publishing it again is one click and no draft state is kept.
 */
export async function unpublishListingFaq(
  context: ActionContext,
  faqId: ListingFaqId,
): Promise<void> {
  await actionStory<number>(
    context,
    {
      event: 'faq.unpublish',
      will: { msg: 'taking the answered question off the listing', data: { listing_faq_id: faqId } },
      ended: (removed) => ({
        phase: 'did',
        msg:
          removed === 0
            ? 'no such answered question was published'
            : 'took the answered question off the listing',
        data: { listing_faq_id: faqId, removed_rows: removed },
      }),
    },
    async ({ db }) => {
      const deleted = await db.deleteFrom('listingFaqs').where('id', '=', faqId).executeTakeFirst()

      return Number(deleted.numDeletedRows)
    },
  )
}

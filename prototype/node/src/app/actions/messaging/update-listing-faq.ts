import type { ListingFaqId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import type { FaqDraft } from '../../core/messaging/faq-draft.ts'
import type { ListingFaq } from '../../db/commerce-schema.ts'

export type UpdateListingFaqInput = {
  faqId: ListingFaqId
  draft: FaqDraft
}

/** Rewords a published entry. The listing it belongs to never moves. */
export async function updateListingFaq(
  { db }: ActionContext,
  input: UpdateListingFaqInput,
): Promise<ListingFaq> {
  return db
    .updateTable('listingFaqs')
    .set({ question: input.draft.question, answer: input.draft.answer })
    .where('id', '=', input.faqId)
    .returningAll()
    .executeTakeFirstOrThrow()
}

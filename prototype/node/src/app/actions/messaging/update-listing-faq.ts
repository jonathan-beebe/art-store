import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import type { FaqDraft } from '../../core/messaging/faq-draft.ts'
import type { ListingFaq } from '../../db/commerce-schema.ts'

export type UpdateListingFaqInput = {
  faqId: number
  draft: FaqDraft
}

/** Rewords a published entry. The listing it belongs to never moves. */
export async function updateListingFaq(
  context: ActionContext,
  input: UpdateListingFaqInput,
): Promise<ListingFaq> {
  return runInTransaction(context, async ({ db }) =>
    db
      .updateTable('listingFaqs')
      .set({ question: input.draft.question, answer: input.draft.answer })
      .where('id', '=', input.faqId)
      .returningAll()
      .executeTakeFirstOrThrow(),
  )
}

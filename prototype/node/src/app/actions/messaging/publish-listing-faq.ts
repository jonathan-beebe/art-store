import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import type { FaqDraft } from '../../core/messaging/faq-draft.ts'
import type { ListingFaq } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type PublishListingFaqInput = {
  listingId: number
  draft: FaqDraft
  /** The answer this entry was lifted from, when a thread is where it came from. */
  sourceMessageId?: number
}

/**
 * Puts one answered question on the listing page for everyone. A row exists
 * only while the entry is published, so the storefront reads the table with no
 * predicate of its own.
 */
export async function publishListingFaq(
  context: ActionContext,
  input: PublishListingFaqInput,
): Promise<ListingFaq> {
  return runInTransaction(context, async ({ db, clock }) =>
    db
      .insertInto('listingFaqs')
      .values({
        listingId: input.listingId,
        question: input.draft.question,
        answer: input.draft.answer,
        sourceMessageId: input.sourceMessageId ?? null,
        publishedAt: toTimestamp(clock.now()),
      })
      .returningAll()
      .executeTakeFirstOrThrow(),
  )
}

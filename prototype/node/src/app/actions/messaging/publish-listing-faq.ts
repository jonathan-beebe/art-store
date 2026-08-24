import type { ListingId, MessageId } from '../../core/ids/entity-ids.ts'
import { newId } from '../../ids.ts'
import type { ActionContext } from '../action-context.ts'
import type { FaqDraft } from '../../core/messaging/faq-draft.ts'
import type { ListingFaq } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type PublishListingFaqInput = {
  listingId: ListingId
  draft: FaqDraft
  /** The answer this entry was lifted from, when a thread is where it came from. */
  sourceMessageId?: MessageId
}

/**
 * Puts one answered question on the listing page for everyone. A row exists
 * only while the entry is published, so the storefront reads the table with no
 * predicate of its own.
 */
export async function publishListingFaq(
  { db, clock }: ActionContext,
  input: PublishListingFaqInput,
): Promise<ListingFaq> {
  return db
    .insertInto('listingFaqs')
    .values({
      id: newId('faq', clock.now()),
      listingId: input.listingId,
      question: input.draft.question,
      answer: input.draft.answer,
      sourceMessageId: input.sourceMessageId ?? null,
      publishedAt: toTimestamp(clock.now()),
    })
    .returningAll()
    .executeTakeFirstOrThrow()
}

import type { ListingId, MessageId } from '../../core/ids/entity-ids.ts'
import { newId } from '../../ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
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
  context: ActionContext,
  input: PublishListingFaqInput,
): Promise<ListingFaq> {
  return actionStory<ListingFaq>(
    context,
    {
      event: 'faq.publish',
      will: {
        msg: 'publishing an answered question on the listing',
        data: { listing_id: input.listingId, source_message_id: input.sourceMessageId ?? null },
      },
      ended: (faq) => ({
        phase: 'did',
        msg: 'published the answered question',
        data: { listing_faq_id: faq.id, listing_id: faq.listingId },
      }),
    },
    ({ db, clock }) =>
      db
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
        .executeTakeFirstOrThrow(),
  )
}

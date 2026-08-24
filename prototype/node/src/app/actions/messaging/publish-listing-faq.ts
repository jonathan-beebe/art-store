import type { ListingId, MessageId } from '../../core/ids/entity-ids.ts'
import { newId } from '../../ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
import type { FaqDraft } from '../../core/messaging/faq-draft.ts'
import { TransitionError } from '../../core/transition-error.ts'
import type { ListingFaq } from '../../db/commerce-schema.ts'
import type { AppDatabase } from '../../db/database.ts'
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
 *
 * A message already published is refused rather than published a second time:
 * `(listing_id, source_message_id)` is unique, and the seller who tries again
 * reads why on the page they tried it from rather than seeing a duplicate
 * entry appear. A draft with no source — one written by hand rather than
 * lifted from a thread — carries no such limit.
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
    async ({ db, clock }) => {
      if (input.sourceMessageId !== undefined) {
        await refuseUnlessUnpublished(db, input.listingId, input.sourceMessageId)
      }

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
    },
  )
}

async function refuseUnlessUnpublished(
  db: AppDatabase,
  listingId: ListingId,
  sourceMessageId: MessageId,
): Promise<void> {
  const already = await db
    .selectFrom('listingFaqs')
    .select('id')
    .where('listingId', '=', listingId)
    .where('sourceMessageId', '=', sourceMessageId)
    .executeTakeFirst()

  if (already !== undefined) {
    throw new TransitionError('That question is already published to the listing.')
  }
}

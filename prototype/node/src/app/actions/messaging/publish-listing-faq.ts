import type { ListingId, MessageId } from '../../core/ids/entity-ids.ts'
import { newId } from '../../ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
import type { FaqDraft } from '../../core/messaging/faq-draft.ts'
import { refused, type Refusal } from '../../core/refusal.ts'
import type { ListingFaq } from '../../db/commerce-schema.ts'
import type { AppDatabase } from '../../db/database.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type PublishListingFaqInput = {
  listingId: ListingId
  draft: FaqDraft
  /** The answer this entry was lifted from, when a thread is where it came from. */
  sourceMessageId?: MessageId
}

export type PublishListingFaqResult = { outcome: 'published'; faq: ListingFaq } | Refusal<'already_published'>

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
): Promise<PublishListingFaqResult> {
  return actionStory<PublishListingFaqResult>(
    context,
    {
      event: 'faq.publish',
      will: {
        msg: 'publishing an answered question on the listing',
        data: { listing_id: input.listingId, source_message_id: input.sourceMessageId ?? null },
      },
      refusedMsg: 'the question is already published to the listing',
      ended: (result) => ({
        phase: 'did',
        msg: 'published the answered question',
        data: { listing_faq_id: result.faq.id, listing_id: result.faq.listingId },
      }),
    },
    async ({ db, clock }) => {
      if (input.sourceMessageId !== undefined) {
        const refusal = await alreadyPublishedRefusal(db, input.listingId, input.sourceMessageId)
        if (refusal !== null) return refusal
      }

      const faq = await db
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

      return { outcome: 'published', faq }
    },
  )
}

async function alreadyPublishedRefusal(
  db: AppDatabase,
  listingId: ListingId,
  sourceMessageId: MessageId,
): Promise<Refusal<'already_published'> | null> {
  const already = await db
    .selectFrom('listingFaqs')
    .select('id')
    .where('listingId', '=', listingId)
    .where('sourceMessageId', '=', sourceMessageId)
    .executeTakeFirst()

  if (already === undefined) return null

  return refused('already_published', {
    listing_id: listingId,
    source_message_id: sourceMessageId,
    listing_faq_id: already.id,
  })
}

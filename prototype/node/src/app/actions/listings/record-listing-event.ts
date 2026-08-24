import type { CustomerId, ListingId } from '../../core/ids/entity-ids.ts'
import { newId } from '../../ids.ts'
import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import type { ListingEventType } from '../../core/listings/listing-event-type.ts'
import { isRecordedOncePerHour, viewWindowStart } from '../../core/listings/listing-view-window.ts'
import type { ListingEvent } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'
import type { AppDatabase } from '../../db/database.ts'

export type RecordListingEventInput = {
  listingId: ListingId
  customerId: CustomerId | null
  eventType: ListingEventType
}

/**
 * Files one interaction with a listing. An event the core collapses per hour
 * returns null on its second write in a window rather than a row, so the caller
 * can tell what it recorded.
 */
export async function recordListingEvent(
  context: ActionContext,
  input: RecordListingEventInput,
): Promise<ListingEvent | null> {
  return runInTransaction(context, async ({ db, clock }) => {
    const now = clock.now()
    if (isRecordedOncePerHour(input.eventType) && (await hasEventThisHour(db, input, now))) {
      return null
    }

    return db
      .insertInto('listingEvents')
      .values({ id: newId('lev', now), ...input, occurredAt: toTimestamp(now) })
      .returningAll()
      .executeTakeFirstOrThrow()
  })
}

async function hasEventThisHour(
  db: AppDatabase,
  input: RecordListingEventInput,
  now: Date,
): Promise<boolean> {
  const seen = await db
    .selectFrom('listingEvents')
    .select('id')
    .where('listingId', '=', input.listingId)
    .where('eventType', '=', input.eventType)
    .where('occurredAt', '>=', toTimestamp(viewWindowStart(now)))
    .where((eb) =>
      input.customerId === null
        ? eb('customerId', 'is', null)
        : eb('customerId', '=', input.customerId),
    )
    .executeTakeFirst()

  return seen !== undefined
}

import type { AdminId, ListingId } from '../../core/ids/entity-ids.ts'
import { newId } from '../../ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
import { activeListingRemoval } from './active-listing-removal.ts'
import type { RemovalKind } from '../../core/moderation/listing-removal.ts'
import { refused, type Refusal } from '../../core/refusal.ts'
import type { ListingRemoval } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type RemoveListingInput = {
  listingId: ListingId
  adminId: AdminId
  kind: RemovalKind
  reason: string
}

export type RemoveListingResult =
  | { outcome: 'removed'; removal: ListingRemoval }
  | Refusal<'already_removed'>

/**
 * Takes a listing off the storefront whatever its status. At most one removal
 * is active at a time, so raising a temporary removal to a permanent one is
 * lift then remove — which leaves the seller one reason to read rather than
 * two overlapping ones.
 */
export async function removeListing(
  context: ActionContext,
  input: RemoveListingInput,
): Promise<RemoveListingResult> {
  return actionStory<RemoveListingResult>(
    context,
    {
      event: 'moderation.remove_listing',
      will: {
        msg: 'removing the listing from the storefront',
        data: { listing_id: input.listingId, kind: input.kind },
      },
      refusedMsg: 'the listing cannot be removed',
      ended: (result) => ({
        phase: 'did',
        msg: 'removed the listing from the storefront',
        data: {
          listing_removal_id: result.removal.id,
          listing_id: result.removal.listingId,
          admin_id: result.removal.adminId,
          kind: result.removal.kind,
        },
      }),
    },
    async (transacted) => {
      const { db, clock } = transacted
      const active = await activeListingRemoval(transacted, input.listingId)

      if (active !== null) {
        return refused('already_removed', {
          listing_id: input.listingId,
          listing_removal_id: active.id,
        })
      }

      const removal = await db
        .insertInto('listingRemovals')
        .values({
          id: newId('rmv', clock.now()),
          listingId: input.listingId,
          adminId: input.adminId,
          kind: input.kind,
          reason: input.reason,
          createdAt: toTimestamp(clock.now()),
          liftedAt: null,
        })
        .returningAll()
        .executeTakeFirstOrThrow()

      return { outcome: 'removed', removal }
    },
  )
}

import type { ListingId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
import { activeListingRemoval } from './active-listing-removal.ts'
import { canLiftRemoval } from '../../core/moderation/listing-removal.ts'
import { BrokenContractError } from '../../core/defect.ts'
import { refused, type Refusal } from '../../core/refusal.ts'
import type { ListingRemoval } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type LiftListingRemovalResult =
  | { outcome: 'lifted'; removal: ListingRemoval }
  | Refusal<'not_removed' | 'permanent_removal'>

/**
 * Puts a temporarily removed listing back under its own status. A permanent
 * removal is refused here rather than hidden in the page that offers it, so a
 * stale form cannot undo one.
 */
export async function liftListingRemoval(
  context: ActionContext,
  { listingId }: { listingId: ListingId },
): Promise<LiftListingRemovalResult> {
  return actionStory<LiftListingRemovalResult>(
    context,
    {
      event: 'moderation.lift_listing_removal',
      will: { msg: 'putting the listing back under its own status', data: { listing_id: listingId } },
      refusedMsg: 'the removal cannot be lifted',
      ended: (result) => ({
        phase: 'did',
        msg: 'put the listing back under its own status',
        data: {
          listing_removal_id: result.removal.id,
          listing_id: result.removal.listingId,
          kind: result.removal.kind,
        },
      }),
    },
    async (transacted) => {
      const { db, clock } = transacted
      const active = await activeListingRemoval(transacted, listingId)

      if (active === null) return refused('not_removed', { listing_id: listingId })
      if (!canLiftRemoval(active.kind)) {
        return refused('permanent_removal', { listing_id: listingId, listing_removal_id: active.id })
      }

      const removal = await db
        .updateTable('listingRemovals')
        .set({ liftedAt: toTimestamp(clock.now()) })
        .where('id', '=', active.id)
        .returningAll()
        .executeTakeFirstOrThrow()

      return { outcome: 'lifted', removal }
    },
  )
}

/**
 * Unwraps a `LiftListingRemovalResult` for a caller inside the application
 * that only ever asks to lift a removal that is active and liftable. A
 * refusal reaching here is a broken contract, not a domain outcome to
 * handle.
 */
export function liftedListingRemoval(result: LiftListingRemovalResult): ListingRemoval {
  if (result.outcome === 'lifted') return result.removal

  throw new BrokenContractError(
    result.reason,
    `a listing-removal lift was refused: ${result.reason}`,
    result.data,
  )
}

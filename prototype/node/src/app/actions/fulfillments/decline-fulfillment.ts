import type { FulfillmentId, SellerId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
import { issueRefund, type IssueRefundResult } from '../refunds/issue-refund.ts'

export type DeclineFulfillmentInput = {
  fulfillmentId: FulfillmentId
  sellerId: SellerId
  reason: string
}

/**
 * The seller says they will not ship this. The refund is what actually moves
 * the money and the stock; this is the seller's half of the story, told over
 * the same unit of work so the decline and the refund read back off one
 * `txn_id`.
 */
export async function declineFulfillment(
  context: ActionContext,
  input: DeclineFulfillmentInput,
): Promise<IssueRefundResult> {
  return actionStory<IssueRefundResult>(
    context,
    {
      event: 'fulfillment.decline',
      will: {
        msg: 'declining the fulfillment',
        data: { fulfillment_id: input.fulfillmentId, seller_id: input.sellerId },
      },
      refusedMsg: 'the fulfillment cannot be declined',
      ended: (result) => ({
        phase: 'did',
        msg: 'declined the fulfillment',
        data: {
          fulfillment_id: result.fulfillment.id,
          order_id: result.fulfillment.orderId,
          seller_id: result.fulfillment.sellerId,
          status_from: result.statusFrom,
          status_to: result.fulfillment.status,
          refund_id: result.refund.id,
          amount_cents: result.refund.amountCents,
          reason: result.refund.reason,
        },
      }),
    },
    (transacted) =>
      issueRefund(transacted, {
        fulfillmentId: input.fulfillmentId,
        reason: input.reason,
        issuedBy: { type: 'seller', id: input.sellerId },
      }),
  )
}

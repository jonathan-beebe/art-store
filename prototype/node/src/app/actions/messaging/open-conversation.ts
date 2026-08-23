import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import { planConversation } from '../../core/messaging/conversation-plan.ts'
import {
  conversationSubject,
  missingConversationParts,
  type ConversationOpening,
  type ConversationSubject,
} from '../../core/messaging/conversation-subject.ts'
import type { Conversation } from '../../db/commerce-schema.ts'
import type { AppDatabase } from '../../db/database.ts'
import { toTimestamp } from '../../db/timestamp.ts'

const SUBJECT_COLUMNS = [
  'sellerId',
  'customerId',
  'adminId',
  'listingId',
  'fulfillmentId',
] as const

/**
 * The one thread on a subject, opened if it is not there yet. Every entry point
 * — the admin's seller page, the storefront's question form, an order's
 * "message the seller" — lands on the same conversation, so a reply always
 * reaches the place the last one came from.
 */
export async function openConversation(
  context: ActionContext,
  opening: ConversationOpening,
): Promise<Conversation> {
  const subject = conversationSubject(opening)
  const missing = missingConversationParts(subject)
  if (missing.length > 0) {
    throw new TypeError(`a ${subject.kind} conversation needs ${missing.join(' and ')}`)
  }

  return runInTransaction(context, async ({ db, clock }) => {
    const plan = planConversation(await conversationsOnSubject(db, subject), subject)
    if (plan.outcome === 'reuse') return plan.conversation

    const openedAt = toTimestamp(clock.now())

    return db
      .insertInto('conversations')
      .values({ ...plan.subject, createdAt: openedAt, lastMessageAt: openedAt })
      .returningAll()
      .executeTakeFirstOrThrow()
  })
}

/** Every row that could be the thread, for the plan to match against. */
async function conversationsOnSubject(
  db: AppDatabase,
  subject: ConversationSubject,
): Promise<readonly Conversation[]> {
  let query = db.selectFrom('conversations').selectAll().where('kind', '=', subject.kind)

  for (const column of SUBJECT_COLUMNS) {
    const value = subject[column]
    query = value === null ? query.where(column, 'is', null) : query.where(column, '=', value)
  }

  return query.orderBy('id').execute()
}

import { newId } from '../../ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
import { participantColumnsOf, subjectColumnOf } from '../../core/messaging/conversation-kind.ts'
import { planConversation } from '../../core/messaging/conversation-plan.ts'
import {
  conversationSubject,
  type ConversationOpening,
  type ConversationSubject,
} from '../../core/messaging/conversation-subject.ts'
import type { Conversation } from '../../db/commerce-schema.ts'
import type { AppDatabase } from '../../db/database.ts'
import { toTimestamp } from '../../db/timestamp.ts'

/** The thread, and whether it was already there. */
type OpenedConversation = { conversation: Conversation; wasReused: boolean }

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

  const opened = await actionStory<OpenedConversation>(
    context,
    {
      event: 'conversation.open',
      will: { msg: `opening the ${subject.kind} thread`, data: { kind: subject.kind } },
      ended: ({ conversation, wasReused }) => ({
        phase: 'did',
        msg: wasReused ? 'reused the thread already on this subject' : 'opened the thread',
        data: {
          conversation_id: conversation.id,
          kind: conversation.kind,
          reused: wasReused,
        },
      }),
    },
    async ({ db, clock }) => {
      const plan = planConversation(await conversationsOnSubject(db, subject), subject)
      if (plan.outcome === 'reuse') return { conversation: plan.conversation, wasReused: true }

      const openedAt = toTimestamp(clock.now())

      const conversation = await db
        .insertInto('conversations')
        .values({
          id: newId('cnv', clock.now()),
          ...plan.subject,
          createdAt: openedAt,
          lastMessageAt: openedAt,
        })
        .returningAll()
        .executeTakeFirstOrThrow()

      return { conversation, wasReused: false }
    },
  )

  return opened.conversation
}

/** The columns this kind fills — the columns `KIND_SHAPES` names for it, in `where` form. */
function subjectColumns(subject: ConversationSubject): readonly (keyof ConversationSubject)[] {
  const subjectColumn = subjectColumnOf(subject.kind)
  return subjectColumn === null
    ? participantColumnsOf(subject.kind)
    : [...participantColumnsOf(subject.kind), subjectColumn]
}

/** Every row that could be the thread, for the plan to match against. */
async function conversationsOnSubject(
  db: AppDatabase,
  subject: ConversationSubject,
): Promise<readonly Conversation[]> {
  let query = db.selectFrom('conversations').selectAll().where('kind', '=', subject.kind)

  for (const column of subjectColumns(subject)) {
    const value = subject[column]
    query = value === null ? query.where(column, 'is', null) : query.where(column, '=', value)
  }

  return query.orderBy('createdAt').orderBy('id').execute()
}

import { newId } from '../../ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
import { planConversation } from '../../core/messaging/conversation-plan.ts'
import {
  conversationSubject,
  subjectKey,
  type ConversationOpening,
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
 *
 * Two callers deciding `'open'` from the same read land on the insert below at
 * the same time; `conversations.subject_key` is unique, so only one of them
 * writes a row and the other's insert conflicts. That caller re-reads by
 * `subjectKey` and gets the row the winner just wrote — a reuse, not the
 * constraint error the plan's own read did not see coming.
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
      const key = subjectKey(subject)
      const plan = planConversation(await conversationsOnSubject(db, key), subject)
      if (plan.outcome === 'reuse') return { conversation: plan.conversation, wasReused: true }

      const openedAt = toTimestamp(clock.now())

      const inserted = await db
        .insertInto('conversations')
        .values({
          id: newId('cnv', clock.now()),
          subjectKey: key,
          ...plan.subject,
          createdAt: openedAt,
          lastMessageAt: openedAt,
        })
        .onConflict((oc) => oc.column('subjectKey').doNothing())
        .returningAll()
        .executeTakeFirst()

      if (inserted !== undefined) return { conversation: inserted, wasReused: false }

      const [reread] = await conversationsOnSubject(db, key)
      if (reread === undefined) {
        throw new Error(`conversation insert on subject "${key}" conflicted but no row is there to re-read`)
      }

      return { conversation: reread, wasReused: true }
    },
  )

  return opened.conversation
}

/** The row on this subject, if one is already there — what the plan matches against. */
async function conversationsOnSubject(
  db: AppDatabase,
  key: string,
): Promise<readonly Conversation[]> {
  const row = await db.selectFrom('conversations').selectAll().where('subjectKey', '=', key).executeTakeFirst()

  return row === undefined ? [] : [row]
}

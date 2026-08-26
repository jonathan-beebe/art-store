import type { ConversationId } from '../../core/ids/entity-ids.ts'
import { newId } from '../../ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStory } from '../action-story.ts'
import { notificationRecipient } from '../../core/notifications/recipient-type.ts'
import { notify } from '../notifications/notify.ts'
import { conversationActor, type MessagingActor } from './conversation-actor.ts'
import { conversationTopicOf } from './conversation-topics.ts'
import { conversationAccess, otherParticipants } from '../../core/messaging/conversation-access.ts'
import { conversationPath } from '../../core/messaging/conversation-path.ts'
import { messageBodyError, parseMessageBody } from '../../core/messaging/message-body.ts'
import { newMessageMessage } from '../../core/notifications/notification-message.ts'
import { refused, type Refusal } from '../../core/refusal.ts'
import type { Conversation, Message } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type PostMessageInput = {
  conversationId: ConversationId
  sender: MessagingActor
  body: string
}

export type PostMessageRefusalReason = 'invalid_body' | 'foreign_conversation' | 'account_blocked'

export type PostMessageResult = { outcome: 'posted'; message: Message } | Refusal<PostMessageRefusalReason>

/**
 * Appends one message, moves the thread to the top of every inbox holding it,
 * and tells the other side. A message a participant may not send is refused
 * here rather than at the route, so all three sites refuse it the same way.
 */
export async function postMessage(
  context: ActionContext,
  input: PostMessageInput,
): Promise<PostMessageResult> {
  const bodyError = messageBodyError(input.body)
  if (bodyError !== null) {
    return refused('invalid_body', { conversation_id: input.conversationId })
  }

  return actionStory<PostMessageResult>(
    context,
    {
      event: 'message.post',
      will: {
        msg: 'posting a message to the thread',
        data: {
          conversation_id: input.conversationId,
          sender_type: input.sender.type,
          sender_id: input.sender.id,
        },
      },
      refusedMsg: 'the message may not be posted',
      ended: (result) => ({
        phase: 'did',
        msg: 'posted the message',
        data: {
          message_id: result.message.id,
          conversation_id: result.message.conversationId,
          sender_type: result.message.senderType,
          sender_id: result.message.senderId,
        },
      }),
    },
    async (transaction) => {
      const { db, clock } = transaction
      const conversation = await db
        .selectFrom('conversations')
        .selectAll()
        .where('id', '=', input.conversationId)
        .executeTakeFirstOrThrow()

      const refusal = await sendRefusal(transaction, conversation, input.sender)
      if (refusal !== null) return refusal

      const sentAt = toTimestamp(clock.now())
      const message = await db
        .insertInto('messages')
        .values({
          id: newId('msg', clock.now()),
          conversationId: conversation.id,
          senderType: input.sender.type,
          senderId: input.sender.id,
          body: parseMessageBody(input.body),
          sentAt,
          readAt: null,
        })
        .returningAll()
        .executeTakeFirstOrThrow()

      await db
        .updateTable('conversations')
        .set({ lastMessageAt: sentAt })
        .where('id', '=', conversation.id)
        .execute()

      await notifyOtherSide(transaction, conversation, input.sender)

      return { outcome: 'posted', message }
    },
  )
}

async function sendRefusal(
  context: ActionContext,
  conversation: Conversation,
  sender: MessagingActor,
): Promise<Refusal<'foreign_conversation' | 'account_blocked'> | null> {
  const access = conversationAccess(conversation, await conversationActor(context, sender))
  const data = { conversation_id: conversation.id, sender_type: sender.type, sender_id: sender.id }

  if (!access.mayRead) return refused('foreign_conversation', data)
  if (!access.mayPost) return refused('account_blocked', data)

  return null
}

async function notifyOtherSide(
  context: ActionContext,
  conversation: Conversation,
  sender: MessagingActor,
): Promise<void> {
  const topic = await conversationTopicOf(context, conversation)

  for (const recipient of otherParticipants(conversation, sender)) {
    await notify(context, {
      ...notificationRecipient(recipient),
      message: newMessageMessage(topic, conversationPath(recipient.type, conversation.id)),
    })
  }
}

/** The sentence a refused post shows beside the reply form, the same on
 * every site that takes one. */
export function messagePostRefusalCopy(reason: PostMessageRefusalReason, body: string | undefined): string {
  switch (reason) {
    case 'invalid_body':
      return messageBodyError(body) ?? 'Write a message before sending.'
    case 'foreign_conversation':
      return 'That conversation belongs to someone else.'
    case 'account_blocked':
      return 'This account is blocked and cannot send messages.'
  }
}

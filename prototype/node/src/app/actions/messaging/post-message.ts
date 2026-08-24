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
import { TransitionError } from '../../core/transition-error.ts'
import type { Conversation, Message } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

export type PostMessageInput = {
  conversationId: ConversationId
  sender: MessagingActor
  body: string
}

/**
 * Appends one message, moves the thread to the top of every inbox holding it,
 * and tells the other side. A message a participant may not send is refused
 * here rather than at the route, so all three sites refuse it the same way.
 */
export async function postMessage(
  context: ActionContext,
  input: PostMessageInput,
): Promise<Message> {
  const bodyError = messageBodyError(input.body)
  if (bodyError !== null) throw new TransitionError(bodyError)

  return actionStory<Message>(
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
      ended: (message) => ({
        phase: 'did',
        msg: 'posted the message',
        data: {
          message_id: message.id,
          conversation_id: message.conversationId,
          sender_type: message.senderType,
          sender_id: message.senderId,
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

      await refuseUnlessSendable(transaction, conversation, input.sender)

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

      return message
    },
  )
}

async function refuseUnlessSendable(
  context: ActionContext,
  conversation: Conversation,
  sender: MessagingActor,
): Promise<void> {
  const access = conversationAccess(conversation, await conversationActor(context, sender))

  if (!access.mayRead) throw new TransitionError('That conversation belongs to someone else.')
  if (!access.mayPost) {
    throw new TransitionError('This account is blocked and cannot send messages.')
  }
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

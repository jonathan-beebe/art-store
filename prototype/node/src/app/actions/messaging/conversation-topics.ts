import type { ConversationId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { conversationTopic } from '../../core/messaging/conversation-topic.ts'
import type { Conversation } from '../../db/commerce-schema.ts'

/** The columns a topic is read from, whatever else the caller selected. */
export type TopicColumns = Pick<Conversation, 'id' | 'kind' | 'listingId' | 'fulfillmentId'>

/**
 * What each conversation is about, in the words an inbox row and a "New
 * message" notification both use. Read in one pass over the subject tables so
 * an inbox of any length costs two queries.
 */
export async function conversationTopics(
  { db }: Pick<ActionContext, 'db'>,
  conversations: readonly TopicColumns[],
): Promise<ReadonlyMap<ConversationId, string>> {
  const listingIds = idsOf(conversations, (conversation) => conversation.listingId)
  const fulfillmentIds = idsOf(conversations, (conversation) => conversation.fulfillmentId)

  const listings =
    listingIds.length === 0
      ? []
      : await db.selectFrom('listings').select(['id', 'title']).where('id', 'in', listingIds).execute()

  const fulfillments =
    fulfillmentIds.length === 0
      ? []
      : await db
          .selectFrom('fulfillments')
          .select(['id', 'orderId'])
          .where('id', 'in', fulfillmentIds)
          .execute()

  const titlesById = new Map(listings.map((listing) => [listing.id, listing.title]))
  const orderIdsById = new Map(fulfillments.map((row) => [row.id, row.orderId]))

  return new Map(
    conversations.map((conversation) => [
      conversation.id,
      conversationTopic(conversation.kind, {
        listingTitle: lookup(titlesById, conversation.listingId),
        orderId: lookup(orderIdsById, conversation.fulfillmentId),
      }),
    ]),
  )
}

/** What one conversation is about. */
export async function conversationTopicOf(
  context: Pick<ActionContext, 'db'>,
  conversation: TopicColumns,
): Promise<string> {
  const topics = await conversationTopics(context, [conversation])

  return topics.get(conversation.id) ?? conversationTopic(conversation.kind)
}

/** What one nullable id maps to, or null when the id or its row is absent. */
function lookup<Id extends string, Value>(byId: ReadonlyMap<Id, Value>, id: Id | null): Value | null {
  return id === null ? null : (byId.get(id) ?? null)
}

function idsOf<Id extends string>(
  conversations: readonly TopicColumns[],
  column: (conversation: TopicColumns) => Id | null,
): readonly Id[] {
  return [...new Set(conversations.map(column).filter((id) => id !== null))]
}

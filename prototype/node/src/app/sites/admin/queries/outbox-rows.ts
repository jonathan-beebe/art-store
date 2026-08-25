import type { ActionContext } from '../../../actions/action-context.ts'
import type { OutboxMessageId } from '../../../core/ids/entity-ids.ts'
import type { ListPage } from '../../../core/paging/list-page.ts'
import { toCount } from '../../../db/count.ts'
import type { OutboxMessage } from '../../../db/commerce-schema.ts'
import type { Timestamp } from '../../../db/timestamp.ts'

/** An outbox message as the list shows it, without the body and link the detail page renders. */
export type OutboxListRow = {
  id: OutboxMessageId
  recipient: string
  subject: string
  createdAt: Timestamp
  deliveredAt: Timestamp | null
}

/** How many messages the outbox holds, independent of which page is shown. */
export async function countOutboxRows(context: Pick<ActionContext, 'db'>): Promise<number> {
  const counted = await context.db
    .selectFrom('outboxMessages')
    .select(({ fn }) => fn.countAll<string | number | bigint>().as('total'))
    .executeTakeFirstOrThrow()

  return toCount(counted.total)
}

/** One page of queued messages, newest first. */
export async function outboxRows(
  { db }: Pick<ActionContext, 'db'>,
  page: Pick<ListPage, 'offset' | 'limit'>,
): Promise<readonly OutboxListRow[]> {
  return db
    .selectFrom('outboxMessages')
    .select(['id', 'recipient', 'subject', 'createdAt', 'deliveredAt'])
    .orderBy('createdAt', 'desc')
    .orderBy('id', 'desc')
    .offset(page.offset)
    .limit(page.limit)
    .execute()
}

/** One queued message, or null where the id names nothing. */
export async function outboxRow(
  { db }: Pick<ActionContext, 'db'>,
  id: OutboxMessageId,
): Promise<OutboxMessage | null> {
  const row = await db
    .selectFrom('outboxMessages')
    .selectAll()
    .where('id', '=', id)
    .executeTakeFirst()

  return row ?? null
}

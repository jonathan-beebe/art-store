import type { ActionContext } from '../../../actions/action-context.ts'
import type { OutboxMessage } from '../../../db/commerce-schema.ts'

/** Every queued message, newest first. */
export async function outboxRows({
  db,
}: Pick<ActionContext, 'db'>): Promise<readonly OutboxMessage[]> {
  return db.selectFrom('outboxMessages').selectAll().orderBy('id', 'desc').execute()
}

/** One queued message, or null where the id names nothing. */
export async function outboxRow(
  { db }: Pick<ActionContext, 'db'>,
  id: number,
): Promise<OutboxMessage | null> {
  const row = await db
    .selectFrom('outboxMessages')
    .selectAll()
    .where('id', '=', id)
    .executeTakeFirst()

  return row ?? null
}

import { mkdir, writeFile } from 'node:fs/promises'
import path from 'node:path'
import type { OutboxMessageId } from '../../core/ids/entity-ids.ts'
import type { ActionContext } from '../action-context.ts'
import { actionStep } from '../action-story.ts'
import type { OutboxMessage } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'
import { renderOutboxMessage } from '../../delivery/outbox-message.ts'

export type DrainOutboxOptions = {
  /** Directory the `.eml` files are written into; created if it is missing. */
  outboxDir: string
}

export type DrainedMessage = {
  id: OutboxMessageId
  recipient: string
  subject: string
  file: string
}

/**
 * Writes every pending message out as an `.eml` file and stamps it delivered.
 * Deliberately outside a transaction: the connection is single and synchronous,
 * so holding it across a file write would block every other request, and a
 * message already on disk must not be un-stamped by a later rollback.
 */
export async function drainOutbox(
  context: ActionContext,
  { outboxDir }: DrainOutboxOptions,
): Promise<readonly DrainedMessage[]> {
  const { db, clock } = context
  const pending = await db
    .selectFrom('outboxMessages')
    .selectAll()
    .where('deliveredAt', 'is', null)
    .orderBy('createdAt', 'asc')
    .orderBy('id', 'asc')
    .execute()

  if (pending.length === 0) return []

  await mkdir(outboxDir, { recursive: true })

  const drained: DrainedMessage[] = []
  for (const message of pending) {
    const file = messageFile(outboxDir, message)
    await writeFile(file, renderOutboxMessage(message), 'utf8')
    await db
      .updateTable('outboxMessages')
      .set({ deliveredAt: toTimestamp(clock.now()) })
      .where('id', '=', message.id)
      .execute()

    drained.push({ id: message.id, recipient: message.recipient, subject: message.subject, file })
    actionStep(context, 'notification.deliver', {
      msg: `wrote ${file}`,
      data: { outbox_message_id: message.id, file },
    })
  }

  return drained
}

function messageFile(outboxDir: string, message: OutboxMessage): string {
  return path.join(outboxDir, `${message.id}.eml`)
}

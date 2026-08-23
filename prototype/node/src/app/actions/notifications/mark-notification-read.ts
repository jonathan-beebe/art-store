import type { ActionContext } from '../action-context.ts'
import { runInTransaction } from '../transaction.ts'
import type { Notification } from '../../db/commerce-schema.ts'
import { toTimestamp } from '../../db/timestamp.ts'

/** Stamps a notification read. A second read keeps the first moment. */
export async function markNotificationRead(
  context: ActionContext,
  notificationId: number,
): Promise<Notification> {
  return runInTransaction(context, async ({ db, clock }) => {
    await db
      .updateTable('notifications')
      .set({ readAt: toTimestamp(clock.now()) })
      .where('id', '=', notificationId)
      .where('readAt', 'is', null)
      .execute()

    return db
      .selectFrom('notifications')
      .selectAll()
      .where('id', '=', notificationId)
      .executeTakeFirstOrThrow()
  })
}

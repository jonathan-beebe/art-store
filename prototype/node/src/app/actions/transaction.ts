import type { ActionContext } from './action-context.ts'
import type { AppDatabase } from '../db/database.ts'
import { newId } from '../ids.ts'
import type { AppLogger } from '../log-story.ts'

/**
 * Runs `work` inside one transaction, joining the caller's if there already is
 * one. SQLite refuses a nested `BEGIN`, so an action that calls another action
 * has to pass its own handle down rather than open a second transaction.
 *
 * Opening one also opens a `txn_id`: the logger the work receives carries it,
 * and so does every line written from anywhere inside the unit of work. An
 * action that joins its caller's transaction joins its caller's `txn_id` too,
 * so one checkout reads back as one id however many actions it ran.
 */
export async function runInTransaction<Result>(
  context: ActionContext,
  work: (context: ActionContext) => Promise<Result>,
): Promise<Result> {
  if (context.db.isTransaction) return work(context)

  return context.db
    .transaction()
    .execute(async (transaction: AppDatabase) =>
      work({ ...context, db: transaction, log: unitOfWorkLog(context) }),
    )
}

function unitOfWorkLog(context: ActionContext): AppLogger | undefined {
  if (context.log === undefined) return undefined

  return context.log.child({ txn_id: newId('txn', context.clock.now()) })
}

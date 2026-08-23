import type { ActionContext } from './action-context.ts'
import type { AppDatabase } from '../db/database.ts'

/**
 * Runs `work` inside one transaction, joining the caller's if there already is
 * one. SQLite refuses a nested `BEGIN`, so an action that calls another action
 * has to pass its own handle down rather than open a second transaction.
 */
export async function runInTransaction<Result>(
  context: ActionContext,
  work: (context: ActionContext) => Promise<Result>,
): Promise<Result> {
  if (context.db.isTransaction) return work(context)

  return context.db.transaction().execute(async (transaction: AppDatabase) => work({ ...context, db: transaction }))
}

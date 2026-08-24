/**
 * An action's story, told over its own unit of work. `actionStory` opens the
 * transaction (or joins the caller's), writes `will`, runs the work, and closes
 * with `did`, `refused`, or `failed` — every line under the same `txn_id`.
 */
import type { ActionContext } from './action-context.ts'
import { runInTransaction } from './transaction.ts'
import {
  logLine,
  logStep,
  SILENT_LOG,
  tellStory,
  type AppLogger,
  type LogData,
  type Story,
  type StoryLine,
} from '../log-story.ts'
import type { LogEvent, LogLineLevel } from '../core/logging/log-event.ts'

/** Where the action writes, or nowhere when its caller kept no log. */
export function actionLogger(context: Pick<ActionContext, 'log'>): AppLogger {
  return context.log ?? SILENT_LOG
}

/** Runs `work` as one unit of work, told as one story. */
export async function actionStory<Result>(
  context: ActionContext,
  story: Story<Result>,
  work: (context: ActionContext) => Promise<Result>,
): Promise<Result> {
  return runInTransaction(context, async (transacted) =>
    tellStory(actionLogger(transacted), story, () => work(transacted)),
  )
}

/** One long step inside a unit of work already under way. */
export function actionStep(
  context: Pick<ActionContext, 'log'>,
  event: LogEvent,
  line: StoryLine,
  level: LogLineLevel = 'info',
): void {
  logStep(actionLogger(context), event, line, level)
}

/**
 * A `did` with no `will` in front of it: a fact worth recording that had no
 * decision to refuse it, such as one migration of a run that already announced
 * itself.
 */
export function actionDid(
  context: Pick<ActionContext, 'log'>,
  event: LogEvent,
  msg: string,
  data: LogData,
  level: LogLineLevel = 'info',
): void {
  logLine(actionLogger(context), level, event, 'did', { msg, data })
}

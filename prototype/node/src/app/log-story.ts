/**
 * The story a write tells: `will` before it, then exactly one of `did`,
 * `refused`, or `failed` after it. `docs/alignment.md` §2.2 is the shape, and
 * reading one `txn_id` back out of the log is what it buys — what was about to
 * happen, what happened, and why it stopped.
 *
 * Nothing here knows about Fastify or the database: it takes a logger and a
 * unit of work, so a request handler, a CLI entrypoint, and a unit test all
 * tell the same story the same way.
 */
import type { LogEvent, LogLineLevel, LogPhase } from './core/logging/log-event.ts'
import { describeError } from './core/logging/logged-error.ts'
import { prefixedMsg } from './core/logging/story-emoji.ts'

/** The entity ids and small facts a line is about. Ids are prefixed ids. */
export type LogData = Record<string, unknown>

/**
 * As much of a logger as anything here needs. Pino's logger and Fastify's
 * per-request child both satisfy it, and so does a test double.
 */
export type AppLogger = {
  debug(payload: object, msg: string): void
  info(payload: object, msg: string): void
  warn(payload: object, msg: string): void
  error(payload: object, msg: string): void
  child(bindings: LogData): AppLogger
}

/** The logger an action writes to when its caller kept no log at all. */
export const SILENT_LOG: AppLogger = {
  debug: () => undefined,
  info: () => undefined,
  warn: () => undefined,
  error: () => undefined,
  child: () => SILENT_LOG,
}

/** One human sentence and the facts behind it. */
export type StoryLine = {
  /** Present tense for `will` and `doing`, past for `did` and `refused`. */
  msg: string
  data?: LogData
}

/** How a unit of work ended: the world changed, or the domain said no. */
export type StoryEnding = StoryLine & { phase: 'did' | 'refused' }

export type Story<Result> = {
  event: LogEvent
  /** What `will`, `doing`, `did`, and `refused` are written at. `failed` is always `error`. */
  level?: LogLineLevel
  /** Marks the story that opens the process (§2.4); exactly one per request or CLI run. */
  root?: boolean
  will: StoryLine
  /** Reads the outcome off the result the work returned. */
  ended: (result: Result) => StoryEnding
}

/** One line, in the payload `docs/alignment.md` §2.1 fixes. */
export function logLine(
  log: AppLogger,
  level: LogLineLevel,
  event: LogEvent,
  phase: LogPhase,
  line: StoryLine,
  durationMs?: number,
  root = false,
): void {
  log[level](
    {
      event,
      phase,
      ...(line.data === undefined ? {} : { data: line.data }),
      ...(durationMs === undefined ? {} : { duration_ms: durationMs }),
    },
    prefixedMsg(line.msg, phase, level, root),
  )
}

/** A long step inside a unit of work — one message of a drain, one order of a sweep. */
export function logStep(
  log: AppLogger,
  event: LogEvent,
  line: StoryLine,
  level: LogLineLevel = 'info',
): void {
  logLine(log, level, event, 'doing', line)
}

/**
 * Runs `work` between the `will` line and the line that closes it. A domain
 * refusal is a returned result `ended` maps to the `refused` phase; a thrown
 * exception always closes the story `failed` at `error`, and either way it
 * goes on to the caller, so logging the story never changes what happens.
 */
export async function tellStory<Result>(
  log: AppLogger,
  story: Story<Result>,
  work: () => Promise<Result>,
): Promise<Result> {
  const level = story.level ?? 'info'
  const root = story.root ?? false
  logLine(log, level, story.event, 'will', story.will, undefined, root)

  const startedAt = performance.now()

  try {
    const result = await work()
    const ending = story.ended(result)

    logLine(log, level, story.event, ending.phase, ending, elapsedMs(startedAt), root)

    return result
  } catch (error) {
    logException(log, story.event, error, elapsedMs(startedAt), root)

    throw error
  }
}

function elapsedMs(startedAt: number): number {
  return Math.round(performance.now() - startedAt)
}

/**
 * `stack` rides on every `failed` line; the logger is configured to drop it
 * outside development, so where the stack is allowed is decided once, where the
 * environment is already known, rather than by every caller.
 */
function logException(
  log: AppLogger,
  event: LogEvent,
  error: unknown,
  durationMs: number,
  root: boolean,
): void {
  const described = describeError(error)

  log.error(
    { event, phase: 'failed', duration_ms: durationMs, error: described },
    prefixedMsg(`the ${event} action failed`, 'failed', 'error', root),
  )
}

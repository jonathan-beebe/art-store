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
import type { Refusal } from './core/refusal.ts'
import { refusalOf } from './core/refusal.ts'
import { BrokenContractError } from './core/defect.ts'

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

/** The results `ended` can be handed: everything the work returns that is not a returned refusal. */
export type Told<Result> = Exclude<Result, Refusal>

type StorySpec<Result> = {
  event: LogEvent
  /** What `will`, `doing`, `did`, and `refused` are written at. `failed` is always `error`. */
  level?: LogLineLevel
  /** Marks the story that opens the process (§2.4); exactly one per request or CLI run. */
  root?: boolean
  will: StoryLine
  /** The one sentence the refused line reads when the work returns a refusal; the machinery adds `data.reason` and the refusal's facts. */
  refusedMsg?: string
  /** Reads the outcome off the result the work returned. A returned refusal never reaches it. */
  ended: (result: Told<Result>) => StoryEnding
}

/**
 * `refusedMsg` is required whenever `Result` carries a `Refusal` arm — the
 * only way the machinery has a sentence to write when the work refuses — and
 * stays optional for a `Result` that never does.
 */
export type Story<Result> = StorySpec<Result> &
  ([Extract<Result, Refusal>] extends [never] ? unknown : { refusedMsg: string })

/** One line, in the payload `docs/alignment.md` §2.1 fixes. */
export function logLine(
  log: AppLogger,
  level: LogLineLevel,
  event: LogEvent,
  phase: LogPhase,
  line: StoryLine,
  { durationMs, root = false }: { durationMs?: number; root?: boolean } = {},
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
  logLine(log, level, story.event, 'will', story.will, { root })

  const startedAt = performance.now()

  try {
    const result = await work()
    const ending = storyEnding(story, result)

    logLine(log, level, story.event, ending.phase, ending, { durationMs: elapsedMs(startedAt), root })

    return result
  } catch (error) {
    logException(log, story.event, error, elapsedMs(startedAt), root)

    throw error
  }
}

/**
 * The refused line writes itself off a returned refusal wherever the story
 * carries a sentence for one; everything else is the story's own `ended` to
 * read. A refusal with nowhere to be told is a caller that promised a
 * sentence and did not supply one, or a result union that should never have
 * carried a `Refusal` arm at all — either way, a defect.
 */
function storyEnding<Result>(story: Story<Result>, result: Result): StoryEnding {
  const refusal = refusalOf(result)
  if (refusal !== null && story.refusedMsg !== undefined) {
    return { phase: 'refused', msg: story.refusedMsg, data: { reason: refusal.reason, ...refusal.data } }
  }
  if (told(result)) return story.ended(result)

  throw new BrokenContractError(
    'unended_refusal',
    `the ${story.event} story has no sentence for the refusal that ended it`,
    { reason: refusal?.reason },
  )
}

/** True when the result is anything but a returned refusal — the shape no success arm uses. */
function told<Result>(result: Result): result is Told<Result> {
  return refusalOf(result) === null
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

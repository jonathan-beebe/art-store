import type pino from 'pino'
import type { LogEvent, LogPhase } from '../core/logging/log-event.ts'
import type { NewLogLine } from '../db/logs-schema.ts'
import { buildTestApp, type TestApp, type TestAppOverrides } from './build-test-app.ts'

/** One captured line, already parsed out of the stream. */
export type LogLine = Record<string, unknown>

export type CapturedLogLines = pino.DestinationStream & {
  /** Every line written so far, parsed as JSON. */
  lines(): LogLine[]
  /** Every line for one event, in the order they were written. */
  linesFor(event: LogEvent): LogLine[]
  /** The one line an event wrote in a phase. Throws when there is none. */
  line(event: LogEvent, phase: LogPhase): LogLine
  /** That line's `data`, for asserting the facts it carries. */
  data(event: LogEvent, phase: LogPhase): Record<string, unknown>
  /** `<event> <phase>` for every line, in order — the story as a reader sees it. */
  story(): string[]
  /** Everything written, as one string, for asserting something never appears. */
  text(): string
}

/**
 * A pino destination that keeps every line written to it in memory, so a
 * test can assert a request or a CLI run logged the structured line it
 * should have.
 */
export function captureLogLines(): CapturedLogLines {
  const raw: string[] = []

  const lines = (): LogLine[] =>
    raw
      .join('')
      .split('\n')
      .filter((line) => line.length > 0)
      .map((line) => JSON.parse(line) as LogLine)

  const linesFor = (event: LogEvent): LogLine[] =>
    lines().filter((line) => line.event === event)

  const line = (event: LogEvent, phase: LogPhase): LogLine => {
    const found = linesFor(event).find((candidate) => candidate.phase === phase)

    if (found === undefined) {
      throw new Error(`no log line carries ${event} in phase ${phase}`)
    }

    return found
  }

  return {
    write(chunk: string): void {
      raw.push(chunk)
    },
    lines,
    linesFor,
    line,
    data(event, phase) {
      const { data } = line(event, phase)

      if (typeof data !== 'object' || data === null) {
        throw new Error(`the ${event} ${phase} line carries no data`)
      }

      return data as Record<string, unknown>
    },
    story: () => lines().map((entry) => `${String(entry.event)} ${String(entry.phase)}`),
    text: () => raw.join(''),
  }
}

/**
 * A row ready for `LogStore.append`, for tests that read the store back.
 * `raw` defaults to the JSON line the fields would have been parsed from, so
 * the any-attribute filter sees the same facts the columns mirror; a test
 * about `raw` itself passes its own.
 */
export function storedLogLine(overrides: Partial<NewLogLine> = {}): NewLogLine {
  const line: NewLogLine = {
    ts: '2026-08-24T12:00:00.000Z',
    level: 'info',
    event: 'order.place',
    phase: 'did',
    msg: 'order placed',
    requestId: 'req-1',
    sessionId: null,
    actorType: null,
    actorId: null,
    txnId: null,
    durationMs: null,
    data: null,
    error: null,
    raw: '',
    ...overrides,
  }

  if (line.raw === '') line.raw = rawOf(line)

  return line
}

/** Each stored column and the §2.1 payload field it mirrors. */
const RAW_FIELD_NAMES = [
  ['level', 'level'],
  ['event', 'event'],
  ['phase', 'phase'],
  ['msg', 'msg'],
  ['requestId', 'request_id'],
  ['sessionId', 'session_id'],
  ['actorType', 'actor_type'],
  ['actorId', 'actor_id'],
  ['txnId', 'txn_id'],
  ['durationMs', 'duration_ms'],
] as const satisfies readonly [keyof NewLogLine, string][]

/** The stdout line the stored fields would have arrived on. */
function rawOf(line: NewLogLine): string {
  const fields: Record<string, unknown> = { ts: line.ts }

  for (const [column, name] of RAW_FIELD_NAMES) {
    const value = line[column]
    if (value !== null) fields[name] = value
  }
  if (line.data !== null) fields.data = JSON.parse(line.data)
  if (line.error !== null) fields.error = JSON.parse(line.error)

  return JSON.stringify(fields)
}

/**
 * The whole app over a captured log, for a test about what a request writes.
 * The capture is named `logLines` rather than `log` so the returned value is
 * still usable as the `ActionContext` a fixture takes.
 */
export async function buildLoggedTestApp(
  overrides: TestAppOverrides = {},
): Promise<TestApp & { logLines: CapturedLogLines }> {
  const logLines = captureLogLines()
  const testApp = await buildTestApp({ logLevel: 'debug', ...overrides, loggerStream: logLines })

  return { ...testApp, logLines }
}

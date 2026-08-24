import type pino from 'pino'
import type { LogEvent, LogPhase } from '../core/logging/log-event.ts'
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

import type pino from 'pino'

export type CapturedLogLines = pino.DestinationStream & {
  /** Every line written so far, parsed as JSON. */
  lines(): Record<string, unknown>[]
}

/**
 * A pino destination that keeps every line written to it in memory, so a
 * test can assert a request or a CLI run logged the structured line it
 * should have.
 */
export function captureLogLines(): CapturedLogLines {
  const raw: string[] = []

  return {
    write(chunk: string): void {
      raw.push(chunk)
    },
    lines(): Record<string, unknown>[] {
      return raw
        .join('')
        .split('\n')
        .filter((line) => line.length > 0)
        .map((line) => JSON.parse(line) as Record<string, unknown>)
    },
  }
}

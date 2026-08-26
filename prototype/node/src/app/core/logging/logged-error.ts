/**
 * What a `failed` line says about the exception behind it.
 */

/** `docs/alignment.md` §2.1: the `error` object, with `stack` kept for development. */
export type LoggedError = {
  type: string
  reason?: string
  message: string
  data?: Record<string, unknown>
  stack?: string
}

/**
 * An exception as the log records it. Anything may be thrown, so a value that
 * is not an `Error` is described by its own type and its text rather than
 * losing the line. `reason` and `data` are read structurally, so any
 * exception carrying them — a `Defect` or a foreign error — is described
 * alike.
 */
export function describeError(error: unknown): LoggedError {
  if (error instanceof Error) {
    const reason = carriedReason(error)
    const data = carriedData(error)

    return {
      type: error.name,
      ...(reason === undefined ? {} : { reason }),
      message: error.message,
      ...(data === undefined ? {} : { data }),
      stack: error.stack,
    }
  }

  return { type: typeof error, message: String(error) }
}

function carriedReason(error: Error): string | undefined {
  const reason = (error as { reason?: unknown }).reason

  return typeof reason === 'string' && reason.length > 0 ? reason : undefined
}

function carriedData(error: Error): Record<string, unknown> | undefined {
  const data = (error as { data?: unknown }).data

  return typeof data === 'object' && data !== null && !Array.isArray(data)
    ? (data as Record<string, unknown>)
    : undefined
}

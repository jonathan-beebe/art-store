/**
 * What a `failed` line says about the exception behind it, and which
 * exceptions are not failures at all.
 */
import { TransitionError } from '../transition-error.ts'

/** `docs/alignment.md` §2.1: the `error` object, with `stack` kept for development. */
export type LoggedError = {
  type: string
  message: string
  stack?: string
}

/**
 * An exception as the log records it. Anything may be thrown, so a value that
 * is not an `Error` is described by its own type and its text rather than
 * losing the line.
 */
export function describeError(error: unknown): LoggedError {
  if (error instanceof Error) {
    return { type: error.name, message: error.message, stack: error.stack }
  }

  return { type: typeof error, message: String(error) }
}

/**
 * Whether an exception is the domain saying no rather than something going
 * wrong. A refusal leaves the world unchanged and is the answer the person who
 * asked for it gets, so it is `refused` at `info` and never a `failed` line
 * anyone should be paged for.
 */
export function isDomainRefusal(error: unknown): boolean {
  return error instanceof TransitionError
}

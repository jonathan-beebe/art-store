/**
 * A defect is a bug surfacing — thrown, logged as `failed`, in contrast to a
 * returned `Refusal` (`core/refusal.ts`) or a thrown `TransitionError`, which
 * are the domain saying no. `reason` is the sub-category within the class,
 * `message` is prose for a person, `data` is prefixed ids and other facts.
 */
export abstract class Defect extends Error {
  readonly reason: string
  readonly data?: Record<string, unknown>

  constructor(reason: string, message: string, data?: Record<string, unknown>) {
    super(message)
    this.name = new.target.name
    this.reason = reason
    if (data !== undefined) this.data = data
  }
}

/** A row or value the code requires is not there. */
export class MissingDataError extends Defect {}

/** The environment or configuration cannot be used. */
export class BadConfigError extends Defect {}

/** A caller broke a function's contract. */
export class BrokenContractError extends Defect {}
